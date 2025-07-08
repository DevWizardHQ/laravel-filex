<?php

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

    public function __construct(FilexService $filexService)
    {
        $this->filexService = $filexService;
    }

    /**
     * Get the temporary storage disk
     */
    protected function getTempDisk()
    {
        return Storage::disk(config('filex.temp_disk', 'local'));
    }

    /**
     * Handle temporary file upload
     */
    public function uploadTemp(Request $request): JsonResponse
    {
        try {
            // Apply performance settings as early as possible
            $memoryLimit = config('filex.performance.memory_limit', '512M');
            $timeLimit = config('filex.performance.time_limit', 600);
            
            ini_set('memory_limit', $memoryLimit);
            set_time_limit($timeLimit);
            
            // Force garbage collection to free up memory
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Get effective upload limits
            $limits = $this->getEffectiveUploadLimits();
            $maxFileSizeKB = round($limits['effective_limit'] / 1024); // Convert to KB for Laravel validation
            
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:' . $maxFileSizeKB,
                'dzuuid' => 'sometimes|string',
                'dzchunkindex' => 'sometimes|integer',
                'dztotalchunkcount' => 'sometimes|integer',
                'dzchunksize' => 'sometimes|integer',
                'dztotalfilesize' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $isChunked = $request->has('dzuuid');

            if ($isChunked) {
                return $this->handleChunkedUpload($request, $file);
            } else {
                return $this->handleSingleUpload($request, $file);
            }

        } catch (\Exception $e) {
            // Log detailed error information for debugging
            Log::error('File upload error: ' . $e->getMessage(), [
                'file' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : 'unknown',
                'file_size' => $request->hasFile('file') ? $request->file('file')->getSize() : null,
                'user_id' => Auth::check() ? Auth::id() : null,
                'request_data' => $request->except(['file']),
                'memory_usage' => memory_get_peak_usage(true),
                'memory_limit' => ini_get('memory_limit'),
                'upload_limits' => $this->getEffectiveUploadLimits(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
                'debug' => config('app.debug') ? [
                    'memory_usage' => memory_get_peak_usage(true),
                    'memory_limit' => ini_get('memory_limit'),
                ] : null
            ], 500);
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

        // Check if all chunks are uploaded
        $uploadedChunks = collect($tempDisk->files($tempDir))
            ->filter(function ($file) {
                return str_contains($file, 'chunk_');
            })
            ->count();

        if ($uploadedChunks === $totalChunks) {
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
                    'message' => $validation['message']
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
                'message' => 'File uploaded successfully'
            ]);
        }

        // Return partial success for chunk upload
        return response()->json([
            'success' => true,
            'chunk' => $chunkIndex,
            'totalChunks' => $totalChunks,
            'message' => 'Chunk uploaded successfully'
        ]);
    }

    /**
     * Handle single file upload
     */
    protected function handleSingleUpload(Request $request, $file): JsonResponse
    {
        $originalFileName = $file->getClientOriginalName();
        $fileName = $this->filexService->generateFileName($originalFileName);
        
        // Store file using streaming to avoid memory issues
        $tempPath = $this->filexService->storeStream(
            $file, 
            'temp', 
            config('filex.temp_disk', 'local'),
            $fileName
        );

        // Validate uploaded file
        $validation = $this->filexService->validateTemp($tempPath, $originalFileName);
        if (!$validation['valid']) {
            $this->getTempDisk()->delete($tempPath);
            return response()->json([
                'success' => false,
                'message' => $validation['message']
            ], 422);
        }

        // Mark file with metadata
        $this->filexService->markTemp($tempPath, [
            'original_name' => $originalFileName,
            'uploaded_at' => now(),
            'user_id' => Auth::check() ? Auth::id() : null,
            'session_id' => session()->getId(),
        ]);

        return response()->json([
            'success' => true,
            'tempPath' => $tempPath,
            'originalName' => $originalFileName,
            'size' => $file->getSize(),
            'message' => 'File uploaded successfully'
        ]);
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
            if (!str_starts_with($tempPath, 'temp/') || 
                str_contains($filename, '..') || 
                str_contains($filename, '/') ||
                empty($filename)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file path'
                ], 403);
            }

            // Verify file ownership/session
            $metadata = $this->filexService->getTempMeta($tempPath);
            if ($metadata && $metadata['user_id'] !== Auth::id() && $metadata['session_id'] !== session()->getId()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Delete file and metadata
            $deleted = $this->filexService->deleteTemp($tempPath);

            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'File deleted successfully' : 'File not found'
            ]);

        } catch (\Exception $e) {
            Log::error('Temp file deletion error: ' . $e->getMessage(), [
                'temp_path' => $request->input('tempPath'),
                'user_id' => Auth::check() ? Auth::id() : null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Deletion failed'
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
        if (!str_starts_with($tempPath, 'temp/') || 
            str_contains($filename, '..') || 
            str_contains($filename, '/') ||
            empty($filename)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid file path'
            ], 403);
        }

        $tempDisk = $this->getTempDisk();
        
        if (!$tempDisk->exists($tempPath)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        $metadata = $this->filexService->getTempMeta($tempPath);

        return response()->json([
            'success' => true,
            'tempPath' => $tempPath,
            'size' => $tempDisk->size($tempPath),
            'metadata' => $metadata
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
     * Get PHP upload limits and ensure our config doesn't exceed them
     */
    protected function getEffectiveUploadLimits(): array
    {
        // Convert PHP ini values to bytes
        $uploadMaxFilesize = $this->convertToBytes(ini_get('upload_max_filesize'));
        $postMaxSize = $this->convertToBytes(ini_get('post_max_size'));
        $memoryLimit = $this->convertToBytes(ini_get('memory_limit'));
        
        // Get our configured limit
        $ourMaxSize = config('filex.max_file_size', 10) * 1024 * 1024; // Convert MB to bytes
        
        // Use the most restrictive limit
        $effectiveLimit = min($uploadMaxFilesize, $postMaxSize, $ourMaxSize);
        
        return [
            'upload_max_filesize' => $uploadMaxFilesize,
            'post_max_size' => $postMaxSize,
            'memory_limit' => $memoryLimit,
            'our_max_size' => $ourMaxSize,
            'effective_limit' => $effectiveLimit,
            'effective_limit_mb' => round($effectiveLimit / (1024 * 1024), 2)
        ];
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
            return response()->json(['error' => 'Not available in production'], 403);
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
                return $this->errorResponse('File too large for single upload. Please use chunked upload.', 413);
            }

            return $isChunked 
                ? $this->handleChunkedUpload($request, $file)
                : $this->handleSingleUploadOptimized($request, $file);

        } catch (\Exception $e) {
            return $this->handleUploadError($e, $request);
        }
    }

    /**
     * Apply performance settings with caching
     */
    protected function applyPerformanceSettings(): void
    {
        static $settingsApplied = false;
        
        if (!$settingsApplied) {
            $memoryLimit = config('filex.performance.memory_limit', '1G');
            $timeLimit = config('filex.performance.time_limit', 600);
            
            ini_set('memory_limit', $memoryLimit);
            set_time_limit($timeLimit);
            
            // Optimize PHP for file operations
            ini_set('auto_detect_line_endings', '0');
            ini_set('default_socket_timeout', '300');
            
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            $settingsApplied = true;
        }
    }

    /**
     * Lightweight validation for basic upload requirements
     */
    protected function validateBasicUpload(Request $request): array
    {
        if (!$request->hasFile('file')) {
            return ['valid' => false, 'message' => 'No file provided'];
        }

        $file = $request->file('file');
        if (!$file->isValid()) {
            return ['valid' => false, 'message' => 'Invalid file upload'];
        }

        // Quick size check before heavy validation
        $limits = $this->getEffectiveUploadLimits();
        if ($file->getSize() > $limits['effective_limit']) {
            return ['valid' => false, 'message' => 'File exceeds size limit'];
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
            'message' => 'File uploaded successfully'
        ]);
    }

    /**
     * Standardized error response
     */
    protected function errorResponse(string $message, int $code = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ], $code);
    }

    /**
     * Handle upload errors
     */
    protected function handleUploadError(\Exception $e, $request): JsonResponse
    {
        Log::error('File upload error: ' . $e->getMessage(), [
            'exception' => $e,
            'request' => $request->all()
        ]);

        return $this->errorResponse('Upload failed: ' . $e->getMessage(), 500);
    }
}
