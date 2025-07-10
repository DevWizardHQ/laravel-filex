<?php

declare(strict_types=1);

namespace DevWizard\Filex\Http\Controllers;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache; // Added Cache facade

class FilexController extends Controller
{
    protected $filexService;

    /**
     * Static cache for performance settings
     */
    private static bool $performanceApplied = false;

    /**
     * Cache for upload limits
     */
    private static ?array $uploadLimits = null;

    public function __construct(FilexService $filexService)
    {
        $this->filexService = $filexService;
    }

    /**
     * Get the temporary storage disk with caching
     */
    protected function getTempDisk()
    {
        static $tempDisk = null;

        if ($tempDisk === null) {
            $tempDisk = Storage::disk(config('filex.temp_disk', 'local'));
        }

        return $tempDisk;
    }

    /**
     * Handle temporary file upload
     */
    public function uploadTemp(Request $request): JsonResponse
    {
        try {
            $this->applyPerformanceSettings();

            // Basic validation first
            $basicValidation = $this->validateBasicUpload($request);
            if (!$basicValidation['valid']) {
                return $this->errorResponse($basicValidation['message'], 422);
            }

            $file = $request->file('file');
            $isChunked = $request->has('dzuuid');

            return $isChunked
                ? $this->handleChunkedUpload($request, $file)
                : $this->handleSingleUploadOptimized($request, $file);
        } catch (\Exception $e) {
            return $this->handleUploadError($e, $request);
        }
    }

    /**
     * Handle chunked file upload with optimized streaming
     */
    protected function handleChunkedUpload(Request $request, $file): JsonResponse
    {
        $uuid = $request->input('dzuuid');
        $chunkIndex = (int) $request->input('dzchunkindex', 0);
        $totalChunks = (int) $request->input('dztotalchunkcount', 1);
        $originalFileName = $file->getClientOriginalName();

        // Create temp directory for chunks with proper permissions
        $tempDir = 'temp/chunks/' . $uuid;
        $chunkPath = $tempDir . '/chunk_' . $chunkIndex;
        $tempDisk = $this->getTempDisk();

        try {
            // Monitor performance
            $startTime = microtime(true);
            $initialMemory = memory_get_usage(true);

            // Ensure chunk directory exists with proper permissions
            $chunkDir = dirname($tempDisk->path($chunkPath));
            if (!is_dir($chunkDir)) {
                mkdir($chunkDir, 0755, true);
            }

            // Calculate optimal buffer size based on chunk size
            $chunkSize = $file->getSize();
            $bufferSize = $this->calculateOptimalBufferSize($chunkSize);

            // Stream the chunk with progress monitoring
            $this->streamChunkWithMonitoring(
                $file->getRealPath(),
                $tempDisk->path($chunkPath),
                $bufferSize,
                $chunkSize
            );

            // Log performance metrics
            $this->logUploadMetrics(
                $startTime,
                $initialMemory,
                $chunkSize,
                $chunkIndex,
                $totalChunks
            );

            // Check if all chunks are uploaded
            $uploadedChunks = $this->getUploadedChunksCount($tempDir);

            if ($uploadedChunks === $totalChunks) {
                return $this->finalizeChunkedUpload(
                    $tempDir,
                    $originalFileName,
                    $totalChunks,
                    $request
                );
            }

            // Return partial success for chunk upload
            return response()->json([
                'success' => true,
                'chunk' => $chunkIndex,
                'totalChunks' => $totalChunks,
                'progress' => round(($uploadedChunks / $totalChunks) * 100, 2),
                'message' => __('filex::translations.chunk_uploaded', [
                    'chunk' => $chunkIndex + 1,
                    'total' => $totalChunks
                ]),
                'upload_type' => 'chunk_partial',
                'metrics' => $this->getUploadMetrics(),
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            // Clean up on error
            $this->cleanupChunkOnError($tempDisk, $chunkPath, $e);

            return $this->errorResponse(
                __('filex::translations.chunk_upload_failed', ['chunk' => $chunkIndex + 1]),
                500,
                'chunk_upload_error'
            );
        }
    }

    /**
     * Handle single file upload
     */
    protected function handleSingleUpload(Request $request, $file): JsonResponse
    {
        return $this->handleSingleUploadOptimized($request, $file);
    }

    /**
     * Delete temporary file
     */
    public function deleteTempFile(Request $request, string $filename): JsonResponse
    {
        try {
            // Reconstruct the temp path from the filename
            $tempPath = 'temp/' . $filename;

            // Security check - ensure file is in temp directory and filename is valid
            if (
                !str_starts_with($tempPath, 'temp/') ||
                str_contains($filename, '..') ||
                str_contains($filename, '/') ||
                empty($filename)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => __('filex::translations.invalid_file_path'),
                    'error_type' => 'security_error',
                    'timestamp' => now()->toISOString()
                ], 403);
            }

            // Verify file ownership/session
            $metadata = $this->filexService->getTempMeta($tempPath);
            if ($metadata && $metadata['user_id'] !== Auth::id() && $metadata['session_id'] !== session()->getId()) {
                return response()->json([
                    'success' => false,
                    'message' => __('filex::translations.no_permission_delete'),
                    'error_type' => 'authorization_error',
                    'timestamp' => now()->toISOString()
                ], 403);
            }

            // Check if file exists before attempting deletion
            if (!$this->getTempDisk()->exists($tempPath)) {
                return response()->json([
                    'success' => false,
                    'message' => __('filex::translations.file_not_found_or_deleted'),
                    'error_type' => 'file_not_found',
                    'timestamp' => now()->toISOString()
                ], 404);
            }

            // Delete file and metadata
            $deleted = $this->filexService->deleteTemp($tempPath);

            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? __('filex::translations.file_deleted') : __('filex::translations.file_delete_failed'),
                'filename' => $filename,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Temp file deletion error: ' . $e->getMessage(), [
                'temp_path' => $request->input('tempPath'),
                'filename' => $filename,
                'user_id' => Auth::check() ? Auth::id() : null,
                'exception' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => __('filex::translations.delete_error'),
                'error_type' => 'server_error',
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Get temporary file info
     */
    public function getTempFileInfo(Request $request, string $filename): JsonResponse
    {
        // Reconstruct the temp path from the filename
        $tempPath = 'temp/' . $filename;

        // Security check - ensure file is in temp directory and filename is valid
        if (
            !str_starts_with($tempPath, 'temp/') ||
            str_contains($filename, '..') ||
            str_contains($filename, '/') ||
            empty($filename)
        ) {
            return response()->json([
                'success' => false,
                'message' => __('filex::translations.invalid_file_path_requested'),
                'error_type' => 'security_error',
                'timestamp' => now()->toISOString()
            ], 403);
        }

        $tempDisk = $this->getTempDisk();

        if (!$tempDisk->exists($tempPath)) {
            return response()->json([
                'success' => false,
                'message' => __('filex::translations.file_not_found_or_expired'),
                'error_type' => 'file_not_found',
                'timestamp' => now()->toISOString()
            ], 404);
        }

        $metadata = $this->filexService->getTempMeta($tempPath);

        return response()->json([
            'success' => true,
            'tempPath' => $tempPath,
            'size' => $tempDisk->size($tempPath),
            'metadata' => $metadata,
            'human_readable_size' => $this->formatBytes($tempDisk->size($tempPath)),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Merge chunks using streaming to avoid memory exhaustion
     */
    protected function mergeChunksStreaming(string $tempDir, string $finalPath, int $totalChunks): void
    {
        $tempDisk = $this->getTempDisk();
        $finalFullPath = $tempDisk->path($finalPath);

        // Ensure the directory exists
        $directory = dirname($finalFullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $outputHandle = fopen($finalFullPath, 'wb');

        if (!$outputHandle) {
            throw new \RuntimeException('Could not open output file for writing');
        }

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFile = $tempDir . '/chunk_' . $i;

                if ($tempDisk->exists($chunkFile)) {
                    $chunkFullPath = $tempDisk->path($chunkFile);
                    $inputHandle = fopen($chunkFullPath, 'rb');

                    if (!$inputHandle) {
                        throw new \RuntimeException("Could not open chunk file: {$chunkFile}");
                    }

                    // Stream copy in 8KB chunks to avoid memory issues
                    while (!feof($inputHandle)) {
                        $data = fread($inputHandle, 8192);
                        if ($data === false) {
                            break;
                        }
                        fwrite($outputHandle, $data);
                    }

                    fclose($inputHandle);
                }
            }
        } finally {
            fclose($outputHandle);
        }
    }

    /**
     * Get PHP upload limits with caching
     */
    protected function getEffectiveUploadLimits(): array
    {
        if (self::$uploadLimits !== null) {
            return self::$uploadLimits;
        }

        // Convert PHP ini values to bytes with caching
        $uploadMaxFilesize = $this->convertToBytes(ini_get('upload_max_filesize'));
        $postMaxSize = $this->convertToBytes(ini_get('post_max_size'));
        $memoryLimit = $this->convertToBytes(ini_get('memory_limit'));

        // Get our configured limit
        $ourMaxSize = config('filex.max_file_size', 10) * 1024 * 1024; // Convert MB to bytes

        // Use the most restrictive limit
        $effectiveLimit = min($uploadMaxFilesize, $postMaxSize, $ourMaxSize);

        self::$uploadLimits = [
            'upload_max_filesize' => $uploadMaxFilesize,
            'post_max_size' => $postMaxSize,
            'memory_limit' => $memoryLimit,
            'our_max_size' => $ourMaxSize,
            'effective_limit' => $effectiveLimit,
            'effective_limit_mb' => round($effectiveLimit / (1024 * 1024), 2)
        ];

        return self::$uploadLimits;
    }

    /**
     * Convert PHP ini size values to bytes
     */
    protected function convertToBytes(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $number = (int) $size;

        switch ($last) {
            case 'g':
                $number *= 1024;
                // Fall through
            case 'm':
                $number *= 1024;
                // Fall through
            case 'k':
                $number *= 1024;
        }

        return $number;
    }

    /**
     * Get upload configuration and limits (useful for debugging)
     */
    public function getUploadConfig(): JsonResponse
    {
        if (!config('app.debug')) {
            return response()->json(['error' => __('filex::translations.not_available_production')], 403);
        }

        $limits = $this->getEffectiveUploadLimits();

        return response()->json([
            'php_settings' => [
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'app_settings' => [
                'max_file_size' => config('filex.max_file_size') . 'MB',
                'performance_memory_limit' => config('filex.performance.memory_limit'),
                'performance_time_limit' => config('filex.performance.time_limit'),
                'temp_disk' => config('filex.temp_disk'),
                'default_disk' => config('filex.default_disk'),
            ],
            'effective_limits' => $limits,
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
            ]
        ]);
    }

    /**
     * Optimized file upload with lazy loading and batch processing
     */
    public function uploadTempOptimized(Request $request): JsonResponse
    {
        try {
            // Early optimization: Set limits before any processing
            $this->applyPerformanceSettings();

            // Lazy validation - only validate what we need
            $basicValidation = $this->validateBasicUpload($request);
            if (!$basicValidation['valid']) {
                return $this->errorResponse($basicValidation['message'], 422);
            }

            $file = $request->file('file');
            $isChunked = $request->has('dzuuid');

            // Optimize based on file size
            $fileSize = $file->getSize();
            $chunkThreshold = config('filex.performance.chunk_threshold', 50 * 1024 * 1024); // 50MB

            if ($fileSize > $chunkThreshold && !$isChunked) {
                $maxSizeMB = round($chunkThreshold / (1024 * 1024), 2);
                $fileSizeMB = round($fileSize / (1024 * 1024), 2);
                return $this->errorResponse(
                    __('filex::translations.file_too_large_single', ['size' => $fileSizeMB, 'max' => $maxSizeMB]),
                    413
                );
            }

            return $isChunked
                ? $this->handleChunkedUpload($request, $file)
                : $this->handleSingleUploadOptimized($request, $file);
        } catch (\Exception $e) {
            return $this->handleUploadError($e, $request);
        }
    }

    /**
     * Apply performance settings with caching and optimization
     */
    protected function applyPerformanceSettings(): void
    {
        if (self::$performanceApplied) {
            return;
        }

        $memoryLimit = config('filex.performance.memory_limit', '1G');
        $timeLimit = config('filex.performance.time_limit', 600);

        // Set memory and time limits
        if (ini_get('memory_limit') !== $memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
        }

        if (ini_get('max_execution_time') < $timeLimit) {
            set_time_limit($timeLimit);
        }

        // Optimize PHP settings for file operations
        ini_set('auto_detect_line_endings', '0');
        ini_set('default_socket_timeout', '300');

        // Force garbage collection for memory optimization
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // Enable OPcache optimization if available
        if (function_exists('opcache_get_status') && function_exists('opcache_reset') && opcache_get_status()) {
            opcache_reset();
        }

        self::$performanceApplied = true;
    }

    /**
     * Lightweight validation for basic upload requirements
     */
    protected function validateBasicUpload(Request $request): array
    {
        if (!$request->hasFile('file')) {
            return ['valid' => false, 'message' => __('filex::translations.no_file_selected')];
        }

        $file = $request->file('file');
        if (!$file->isValid()) {
            $error = $file->getError();
            $errorMessage = match ($error) {
                UPLOAD_ERR_INI_SIZE => __('filex::translations.exceeds_server_max'),
                UPLOAD_ERR_FORM_SIZE => __('filex::translations.exceeds_form_max'),
                UPLOAD_ERR_PARTIAL => __('filex::translations.partial_upload'),
                UPLOAD_ERR_NO_FILE => __('filex::translations.no_file_provided'),
                UPLOAD_ERR_NO_TMP_DIR => __('filex::translations.no_tmp_folder'),
                UPLOAD_ERR_CANT_WRITE => __('filex::translations.cannot_write_disk'),
                UPLOAD_ERR_EXTENSION => __('filex::translations.upload_stopped_extension'),
                default => __('filex::translations.unknown_upload_error')
            };

            return ['valid' => false, 'message' => $errorMessage, 'error_code' => $error];
        }

        // Quick size check before heavy validation
        $limits = $this->getEffectiveUploadLimits();
        if ($file->getSize() > $limits['effective_limit']) {
            $maxSizeMB = round($limits['effective_limit'] / (1024 * 1024), 2);
            $fileSizeMB = round($file->getSize() / (1024 * 1024), 2);
            return [
                'valid' => false,
                'message' => __('filex::translations.file_too_large_limit', ['size' => $fileSizeMB, 'max' => $maxSizeMB])
            ];
        }

        return ['valid' => true];
    }

    /**
     * Optimized single file upload with deferred validation
     */
    protected function handleSingleUploadOptimized(Request $request, $file): JsonResponse
    {
        $originalFileName = $file->getClientOriginalName();

        // Store file first using streaming
        $tempPath = $this->filexService->storeOptimized(
            $file,
            'temp',
            config('filex.temp_disk', 'local')
        );

        // Defer expensive validation until after storage
        $validation = $this->filexService->validateDeferred($tempPath, $originalFileName);
        if (!$validation['valid']) {
            $this->getTempDisk()->delete($tempPath);
            return $this->errorResponse($validation['message'], 422);
        }

        // Mark file with metadata
        $this->filexService->markTemp($tempPath, [
            'original_name' => $originalFileName,
            'uploaded_at' => now()->toISOString(),
            'user_id' => Auth::id(),
            'session_id' => session()->getId(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return response()->json([
            'success' => true,
            'tempPath' => $tempPath,
            'originalName' => $originalFileName,
            'size' => $file->getSize(),
            'human_readable_size' => $this->formatBytes($file->getSize()),
            'mime_type' => $file->getMimeType(),
            'upload_type' => 'single',
            'message' => __('filex::translations.upload_success'),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Add request context to responses for better debugging
     */
    protected function addRequestContext(array $response, Request $request): array
    {
        if (config('app.debug', false)) {
            $response['debug_info'] = [
                'request_id' => $request->header('X-Request-ID', uniqid()),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'timestamp' => now()->toISOString()
            ];
        }

        return $response;
    }

    /**
     * Enhanced upload configuration endpoint with rate limiting info
     */
    public function getUploadStatus(): JsonResponse
    {
        $limits = $this->getEffectiveUploadLimits();

        return response()->json([
            'success' => true,
            'upload_status' => 'available',
            'limits' => [
                'max_file_size_mb' => $limits['effective_limit_mb'],
                'max_chunk_size' => config('filex.chunk.size', 1048576),
                'max_parallel_uploads' => config('filex.performance.parallel_uploads', 2),
                'supported_formats' => config('filex.allowed_extensions', []),
                'chunked_upload_threshold_mb' => round(config('filex.performance.chunk_threshold', 50 * 1024 * 1024) / (1024 * 1024), 2)
            ],
            'rate_limiting' => [
                'enabled' => config('filex.rate_limiting.enabled', false),
                'requests_per_minute' => config('filex.rate_limiting.ip_limit', 50),
                'user_requests_per_hour' => config('filex.rate_limiting.user_limit', 100)
            ],
            'server_status' => [
                'disk_space_available' => $this->checkDiskSpace(),
                'memory_available' => $this->formatBytes(memory_get_usage(true)),
                'temp_directory_writable' => is_writable(storage_path('app/temp'))
            ],
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Check available disk space
     */
    protected function checkDiskSpace(): string
    {
        $disk = $this->getTempDisk();
        $path = $disk->path('');

        if (function_exists('disk_free_space')) {
            $freeBytes = disk_free_space($path);
            return $freeBytes ? $this->formatBytes($freeBytes) : 'Unknown';
        }

        return 'Unknown';
    }

    /**
     * Standardized error response
     */
    protected function errorResponse(string $message, int $code = 500, string $errorType = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ];

        if ($errorType) {
            $response['error_type'] = $errorType;
        }

        // Add helpful context for specific error codes
        switch ($code) {
            case 413:
                $response['error_type'] = 'file_too_large';
                $response['help'] = __('filex::translations.help_file_too_large');
                break;
            case 422:
                $response['error_type'] = 'validation_error';
                $response['help'] = __('filex::translations.help_validation_error');
                break;
            case 429:
                $response['error_type'] = 'rate_limit';
                $response['help'] = __('filex::translations.help_rate_limit');
                break;
            case 500:
                $response['error_type'] = 'server_error';
                $response['help'] = __('filex::translations.help_server_error');
                break;
        }

        return response()->json($response, $code);
    }

    /**
     * Handle upload errors
     */
    protected function handleUploadError(\Exception $e, $request): JsonResponse
    {
        Log::error('File upload error: ' . $e->getMessage(), [
            'exception' => $e,
            'request' => $request->all(),
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Determine error type based on exception
        $errorType = 'server_error';
        $message = __('filex::translations.upload_error');

        if (str_contains($e->getMessage(), 'disk space')) {
            $errorType = 'disk_space_error';
            $message = __('filex::translations.disk_space_error');
        } elseif (str_contains($e->getMessage(), 'memory')) {
            $errorType = 'memory_error';
            $message = __('filex::translations.memory_error');
        } elseif (str_contains($e->getMessage(), 'timeout')) {
            $errorType = 'timeout_error';
            $message = __('filex::translations.timeout_error');
        } elseif (str_contains($e->getMessage(), 'permission')) {
            $errorType = 'permission_error';
            $message = __('filex::translations.permission_error');
        }

        return $this->errorResponse($message, 500, $errorType);
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Stream chunk with progress monitoring
     */
    private function streamChunkWithMonitoring(
        string $sourcePath,
        string $targetPath,
        int $bufferSize,
        int $totalSize
    ): void {
        $bytesWritten = 0;
        $lastProgressUpdate = microtime(true);
        $progressInterval = 0.5; // Update progress every 0.5 seconds

        $source = fopen($sourcePath, 'rb');
        $target = fopen($targetPath, 'wb');

        if (!$source || !$target) {
            throw new \RuntimeException('Could not open files for streaming');
        }

        try {
            while (!feof($source)) {
                $buffer = fread($source, $bufferSize);
                if ($buffer === false) break;

                $written = fwrite($target, $buffer);
                if ($written === false) {
                    throw new \RuntimeException('Failed to write chunk data');
                }

                $bytesWritten += $written;

                // Update progress periodically
                $now = microtime(true);
                if (($now - $lastProgressUpdate) >= $progressInterval) {
                    $this->updateUploadProgress($bytesWritten, $totalSize);
                    $lastProgressUpdate = $now;
                }

                // Check memory usage periodically
                if ($bytesWritten % (10 * $bufferSize) === 0) {
                    $this->checkMemoryUsage();
                }
            }
        } finally {
            if ($source) fclose($source);
            if ($target) fclose($target);
        }

        // Verify file size
        if ($bytesWritten !== $totalSize) {
            throw new \RuntimeException('Chunk size mismatch after upload');
        }
    }

    /**
     * Calculate optimal buffer size based on chunk size and available memory
     */
    private function calculateOptimalBufferSize(int $chunkSize): int
    {
        $memoryLimit = $this->getMemoryLimit();
        $currentUsage = memory_get_usage(true);
        $availableMemory = $memoryLimit - $currentUsage;

        // Base calculation on chunk size
        if ($chunkSize < 1024 * 1024) { // < 1MB
            $baseSize = 8192; // 8KB
        } elseif ($chunkSize < 10 * 1024 * 1024) { // < 10MB
            $baseSize = 64 * 1024; // 64KB
        } elseif ($chunkSize < 100 * 1024 * 1024) { // < 100MB
            $baseSize = 256 * 1024; // 256KB
        } else {
            $baseSize = 1024 * 1024; // 1MB
        }

        // Adjust based on available memory (max 5% of available)
        $maxBuffer = (int)($availableMemory * 0.05);
        return min($baseSize, $maxBuffer);
    }

    /**
     * Get memory limit in bytes
     */
    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return PHP_INT_MAX;

        $value = (int)$limit;
        $unit = strtolower(substr($limit, -1));

        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Check memory usage and take action if needed
     */
    private function checkMemoryUsage(): void
    {
        $usage = memory_get_usage(true);
        $limit = $this->getMemoryLimit();
        $percentage = ($usage / $limit) * 100;

        if ($percentage > 90) {
            // Critical: Force cleanup
            gc_collect_cycles();
            $this->clearUploadCache();
            Log::warning('Critical memory usage during upload', [
                'usage' => $this->formatBytes($usage),
                'limit' => $this->formatBytes($limit),
                'percentage' => round($percentage, 2)
            ]);
        } elseif ($percentage > 75) {
            // Warning: Trigger garbage collection
            gc_collect_cycles();
            Log::info('High memory usage during upload', [
                'usage' => $this->formatBytes($usage)
            ]);
        }
    }

    /**
     * Clear upload-related caches
     */
    private function clearUploadCache(): void
    {
        static::$uploadLimits = null;
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * Update upload progress
     */
    private function updateUploadProgress(int $bytesWritten, int $totalSize): void
    {
        $progress = [
            'bytes_written' => $bytesWritten,
            'total_size' => $totalSize,
            'percentage' => round(($bytesWritten / $totalSize) * 100, 2),
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'timestamp' => microtime(true)
        ];

        Cache::put(
            'upload_progress_' . request()->input('dzuuid'),
            $progress,
            now()->addMinutes(5)
        );
    }

    /**
     * Get upload metrics
     */
    private function getUploadMetrics(): array
    {
        return [
            'memory_usage' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'system_load' => sys_getloadavg()[0],
            'timestamp' => (int)microtime(true)
        ];
    }

    /**
     * Log upload performance metrics
     */
    private function logUploadMetrics(
        float $startTime,
        int $initialMemory,
        int $size,
        int $chunkIndex,
        int $totalChunks
    ): void {
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 4);
        $memoryUsed = memory_get_usage(true) - $initialMemory;

        Log::info('Chunk upload metrics', [
            'chunk_index' => $chunkIndex + 1,
            'total_chunks' => $totalChunks,
            'size' => $this->formatBytes($size),
            'duration' => $duration . 's',
            'memory_used' => $this->formatBytes($memoryUsed),
            'throughput' => $this->formatBytes($size / $duration) . '/s'
        ]);
    }

    /**
     * Get count of uploaded chunks
     */
    private function getUploadedChunksCount(string $tempDir): int
    {
        return collect($this->getTempDisk()->files($tempDir))
            ->filter(fn($file) => str_contains($file, 'chunk_'))
            ->count();
    }

    /**
     * Finalize chunked upload
     */
    private function finalizeChunkedUpload(
        string $tempDir,
        string $originalFileName,
        int $totalChunks,
        Request $request
    ): JsonResponse {
        try {
            $startTime = microtime(true);
            $initialMemory = memory_get_usage(true);

            // Generate final filename
            $finalFileName = $this->filexService->generateFileName($originalFileName);
            $finalPath = 'temp/' . $finalFileName;

            // Merge chunks with optimized streaming
            $this->mergeChunksStreaming($tempDir, $finalPath, $totalChunks);

            // Clean up chunks
            $this->getTempDisk()->deleteDirectory($tempDir);

            // Validate merged file
            $validation = $this->filexService->validateTemp($finalPath, $originalFileName);
            if (!$validation['valid']) {
                $this->getTempDisk()->delete($finalPath);
                return response()->json([
                    'success' => false,
                    'message' => $validation['message'],
                    'error_type' => 'validation_error',
                    'timestamp' => now()->toIso8601String()
                ], 422);
            }

            // Mark file with metadata
            $this->filexService->markTemp($finalPath, [
                'original_name' => $originalFileName,
                'uploaded_at' => now(),
                'user_id' => Auth::id(),
                'session_id' => session()->getId(),
                'upload_metrics' => $this->getUploadMetrics()
            ]);

            // Log final metrics
            $this->logUploadMetrics(
                $startTime,
                $initialMemory,
                $this->getTempDisk()->size($finalPath),
                $totalChunks - 1,
                $totalChunks
            );

            return response()->json([
                'success' => true,
                'tempPath' => $finalPath,
                'originalName' => $originalFileName,
                'size' => $this->getTempDisk()->size($finalPath),
                'message' => __('filex::translations.upload_success'),
                'upload_type' => 'chunked',
                'total_chunks' => $totalChunks,
                'metrics' => $this->getUploadMetrics(),
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            // Clean up on error
            $this->getTempDisk()->deleteDirectory($tempDir);
            if (isset($finalPath)) {
                $this->getTempDisk()->delete($finalPath);
            }

            Log::error('Chunk merge error', [
                'error' => $e->getMessage(),
                'total_chunks' => $totalChunks
            ]);

            return $this->errorResponse(
                __('filex::translations.chunk_merge_failed'),
                500,
                'chunk_merge_error'
            );
        }
    }

    /**
     * Clean up chunk on error
     */
    private function cleanupChunkOnError($tempDisk, string $chunkPath, \Exception $e): void
    {
        if (file_exists($tempDisk->path($chunkPath))) {
            unlink($tempDisk->path($chunkPath));
        }

        Log::error('Chunk upload error', [
            'path' => $chunkPath,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
