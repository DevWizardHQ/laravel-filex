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
     * Handle chunked file upload
     */
    protected function handleChunkedUpload(Request $request, $file): JsonResponse
    {
        $uuid = $request->input('dzuuid');
        $chunkIndex = (int) $request->input('dzchunkindex', 0);
        $totalChunks = (int) $request->input('dztotalchunkcount', 1);
        $originalFileName = $file->getClientOriginalName();

        // Create temp directory for chunks
        $tempDir = 'temp/chunks/' . $uuid;
        $chunkPath = $tempDir . '/chunk_' . $chunkIndex;

        // Store the chunk using streaming
        $tempDisk = $this->getTempDisk();
        $chunkFullPath = $tempDisk->path($chunkPath);

        // Ensure chunk directory exists
        $chunkDir = dirname($chunkFullPath);
        if (!is_dir($chunkDir)) {
            mkdir($chunkDir, 0755, true);
        }

        try {
            // Use streaming to store chunk
            $fileStream = fopen($file->getRealPath(), 'rb');
            $chunkStream = fopen($chunkFullPath, 'wb');

            if (!$fileStream || !$chunkStream) {
                throw new \RuntimeException('Could not open files for chunk streaming');
            }

            try {
                // Stream copy in 8KB chunks
                while (!feof($fileStream)) {
                    $data = fread($fileStream, 8192);
                    if ($data === false) break;
                    fwrite($chunkStream, $data);
                }
            } finally {
                if ($fileStream) fclose($fileStream);
                if ($chunkStream) fclose($chunkStream);
            }
        } catch (\Exception $e) {
            // Clean up partial chunk on error
            if (file_exists($chunkFullPath)) {
                unlink($chunkFullPath);
            }

            Log::error('Chunk upload error', [
                'uuid' => $uuid,
                'chunk_index' => $chunkIndex,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse(
                __('filex::translations.chunk_upload_failed', ['chunk' => $chunkIndex]),
                500,
                'chunk_upload_error'
            );
        }

        // Check if all chunks are uploaded
        $uploadedChunks = collect($tempDisk->files($tempDir))
            ->filter(function ($file) {
                return str_contains($file, 'chunk_');
            })
            ->count();

        if ($uploadedChunks === $totalChunks) {
            try {
                // All chunks uploaded, merge them using streaming
                $finalFileName = $this->filexService->generateFileName($originalFileName);
                $finalPath = 'temp/' . $finalFileName;

                // Use streaming to merge chunks to avoid memory exhaustion
                $this->mergeChunksStreaming($tempDir, $finalPath, $totalChunks);

                // Clean up chunks
                $tempDisk->deleteDirectory($tempDir);

                // Validate merged file
                $validation = $this->filexService->validateTemp($finalPath, $originalFileName);
                if (!$validation['valid']) {
                    $tempDisk->delete($finalPath);
                    return response()->json([
                        'success' => false,
                        'message' => $validation['message'] ?? __('filex::translations.validation_failed_after_upload'),
                        'error_type' => 'validation_error',
                        'timestamp' => now()->toISOString()
                    ], 422);
                }

                // Mark file with metadata
                $this->filexService->markTemp($finalPath, [
                    'original_name' => $originalFileName,
                    'uploaded_at' => now(),
                    'user_id' => Auth::check() ? Auth::id() : null,
                    'session_id' => session()->getId(),
                ]);

                return response()->json([
                    'success' => true,
                    'tempPath' => $finalPath,
                    'originalName' => $originalFileName,
                    'size' => $tempDisk->size($finalPath),
                    'message' => __('filex::translations.upload_success'),
                    'upload_type' => 'chunked',
                    'total_chunks' => $totalChunks,
                    'timestamp' => now()->toISOString()
                ]);
            } catch (\Exception $e) {
                // Clean up on merge failure
                $tempDisk->deleteDirectory($tempDir);
                if (isset($finalPath)) {
                    $tempDisk->delete($finalPath);
                }

                Log::error('Chunk merge error', [
                    'uuid' => $uuid,
                    'total_chunks' => $totalChunks,
                    'error' => $e->getMessage()
                ]);

                return $this->errorResponse(
                    __('filex::translations.chunk_merge_failed'),
                    500,
                    'chunk_merge_error'
                );
            }
        }

        // Return partial success for chunk upload
        return response()->json([
            'success' => true,
            'chunk' => $chunkIndex,
            'totalChunks' => $totalChunks,
            'progress' => round(($chunkIndex + 1) / $totalChunks * 100, 2),
            'message' => __('filex::translations.chunk_uploaded', ['chunk' => $chunkIndex, 'total' => $totalChunks]),
            'upload_type' => 'chunk_partial',
            'timestamp' => now()->toISOString()
        ]);
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
}
