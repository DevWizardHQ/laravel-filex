<?php

declare(strict_types=1);

namespace DevWizard\Filex\Services;

use Carbon\Carbon;
use DevWizard\Filex\Support\ByteHelper;
use DevWizard\Filex\Support\ConfigHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FilexService
{
    /**
     * Configuration cache to avoid repeated config() calls
     */
    private static array $configCache = [];

    /**
     * Runtime caches for performance optimization
     */
    private static array $runtimeCaches = [
        'allowedExtensions' => null,
        'allowedMimeTypes' => null,
        'suspiciousPatterns' => null,
        'tempDisk' => null,
        'defaultDisk' => null,
    ];

    /**
     * Dedicated cache arrays for better performance
     */
    private static array $mimeTypeCache = [];

    private static array $bufferSizeCache = [];

    private static array $byteFormatCache = [];

    private static array $signatureCache = [];

    /**
     * Cache size limits to prevent memory leaks
     */
    private const MAX_CACHE_SIZE = [
        'mimeType' => 1000,
        'bufferSize' => 100,
        'byteFormat' => 100,
        'signature' => 500,
    ];

    /**
     * Memory thresholds for monitoring
     */
    private static ?array $memoryThresholds = null;

    /**
     * Performance monitoring
     */
    private static ?int $memoryHighWatermark = null;

    /**
     * Generate a unique filename with better performance
     */
    public function generateFileName(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = pathinfo($originalName, PATHINFO_FILENAME);

        // Use more efficient random generation
        $timestamp = now()->format('YmdHis');
        $random = bin2hex(random_bytes(4)); // More efficient than Str::random(8)

        // Use faster slug generation for performance
        $slugName = $this->fastSlug($name);

        return $slugName.'_'.$timestamp.'_'.$random.'.'.$extension;
    }

    /**
     * Faster slug generation without regex
     */
    private function fastSlug(string $name): string
    {
        // Basic slug generation without heavy regex operations
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }

    /**
     * Clear configuration cache (useful for testing)
     */
    public static function clearConfigCache(): void
    {
        self::$configCache = [];
        self::$runtimeCaches = [
            'allowedExtensions' => null,
            'allowedMimeTypes' => null,
            'suspiciousPatterns' => null,
            'tempDisk' => null,
            'defaultDisk' => null,
        ];
        self::$mimeTypeCache = [];
        self::$bufferSizeCache = [];
        self::$byteFormatCache = [];
        self::$signatureCache = [];
        self::$memoryThresholds = null;
    }

    /**
     * Validate temporary file
     */
    public function validateTemp(string $tempPath, string $originalName): array
    {
        try {
            $tempDisk = $this->getTempDisk();

            if (! $tempDisk->exists($tempPath)) {
                return ['valid' => false, 'message' => __('filex::translations.errors.file_not_found')];
            }

            $fileSize = $tempDisk->size($tempPath);
            $maxSize = ConfigHelper::getMaxFileSize();

            if ($fileSize > $maxSize) {
                return ['valid' => false, 'message' => __('filex::translations.errors.file_size_exceeds_limit')];
            }

            // Get file extension
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (! $this->allowsExtension($extension)) {
                return ['valid' => false, 'message' => __('filex::translations.errors.file_type_not_allowed')];
            }

            // Additional MIME type validation for security
            $tempFilePath = $tempDisk->path($tempPath);
            $mimeType = $this->detectRealMimeType($tempFilePath);

            if (! $this->allowsMimeType($mimeType)) {
                return ['valid' => false, 'message' => __('filex::translations.errors.invalid_file_type_detected')];
            }

            return ['valid' => true, 'message' => __('filex::translations.validation')];
        } catch (\Exception $e) {
            Log::error('File validation error: '.$e->getMessage(), ['temp_path' => $tempPath]);

            return ['valid' => false, 'message' => __('filex::translations.errors.file_validation_failed')];
        }
    }

    /**
     * Mark temporary file with metadata
     */
    public function markTemp(string $tempPath, array $metadata): bool
    {
        try {
            $tempDisk = $this->getTempDisk();
            $metadataPath = $tempPath.'.meta';
            $metadataContent = json_encode(array_merge($metadata, [
                'created_at' => now()->toISOString(),
                'expires_at' => now()->addHours(ConfigHelper::getTempExpiryHours())->toISOString(),
            ]));

            return $tempDisk->put($metadataPath, $metadataContent);
        } catch (\Exception $e) {
            Log::error('Failed to mark temp file: '.$e->getMessage(), ['temp_path' => $tempPath]);

            return false;
        }
    }

    /**
     * Get temporary file metadata
     */
    public function getTempMeta(string $tempPath): ?array
    {
        try {
            $tempDisk = $this->getTempDisk();
            $metadataPath = $tempPath.'.meta';
            if (! $tempDisk->exists($metadataPath)) {
                return null;
            }

            $content = $tempDisk->get($metadataPath);

            return json_decode($content, true);
        } catch (\Exception $e) {
            Log::error('Failed to get temp file metadata: '.$e->getMessage(), ['temp_path' => $tempPath]);

            return null;
        }
    }

    /**
     * Delete temporary file and its metadata
     */
    public function deleteTemp(string $tempPath): bool
    {
        try {
            $tempDisk = $this->getTempDisk();
            $deleted = true;

            if ($tempDisk->exists($tempPath)) {
                $deleted = $tempDisk->delete($tempPath);
            }

            $metadataPath = $tempPath.'.meta';
            if ($tempDisk->exists($metadataPath)) {
                $tempDisk->delete($metadataPath);
            }

            return $deleted;
        } catch (\Exception $e) {
            Log::error('Failed to delete temp file: '.$e->getMessage(), ['temp_path' => $tempPath]);

            return false;
        }
    }

    /**
     * Move files from temp to permanent location
     */
    public function moveFiles(array $tempPaths, string $targetDirectory, ?string $disk = null, ?string $visibility = null): array
    {
        $disk = $disk ?? ConfigHelper::getDefaultDisk();
        $visibility = $visibility ?? ConfigHelper::get('storage.visibility.default', 'public');
        $tempDisk = $this->getTempDisk();
        $results = [];

        foreach ($tempPaths as $tempPath) {
            try {
                if (! str_starts_with($tempPath, 'temp/')) {
                    $results[] = ['success' => false, 'tempPath' => $tempPath, 'message' => 'Invalid temp path'];

                    continue;
                }

                if (! $tempDisk->exists($tempPath)) {
                    $results[] = ['success' => false, 'tempPath' => $tempPath, 'message' => 'Temp file not found'];

                    continue;
                }

                $metadata = $this->getTempMeta($tempPath);
                $originalName = $metadata['original_name'] ?? basename($tempPath);

                // Generate final filename
                $finalFileName = $this->generateFileName($originalName);
                $finalPath = trim($targetDirectory, '/').'/'.$finalFileName;

                // Ensure target directory exists
                $targetDir = dirname($finalPath);
                if (! Storage::disk($disk)->exists($targetDir)) {
                    Storage::disk($disk)->makeDirectory($targetDir);
                }

                // Copy file to final location using streaming to avoid memory issues
                $moved = $this->copyStream($tempDisk, $tempPath, Storage::disk($disk), $finalPath);

                if ($moved) {
                    // Set file visibility
                    try {
                        Storage::disk($disk)->setVisibility($finalPath, $visibility);
                    } catch (\Exception $e) {
                        // Log visibility error but don't fail the upload
                        Log::warning('Failed to set file visibility: '.$e->getMessage(), [
                            'file_path' => $finalPath,
                            'visibility' => $visibility,
                        ]);
                    }

                    // Clean up temp file
                    $this->deleteTemp($tempPath);

                    $results[] = [
                        'success' => true,
                        'tempPath' => $tempPath,
                        'finalPath' => $finalPath,
                        'visibility' => $visibility,
                        'metadata' => $metadata,
                    ];
                } else {
                    $results[] = ['success' => false, 'tempPath' => $tempPath, 'message' => 'Failed to move file'];
                }
            } catch (\Exception $e) {
                Log::error('Failed to move temp file: '.$e->getMessage(), ['temp_path' => $tempPath]);
                $results[] = ['success' => false, 'tempPath' => $tempPath, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Clean up expired temporary files
     */
    public function cleanup(): array
    {
        $tempDisk = $this->getTempDisk();
        $cleaned = [];
        $errors = [];

        try {
            $tempFiles = $tempDisk->allFiles('temp');

            foreach ($tempFiles as $file) {
                // Skip metadata files, they'll be handled with their parent files
                if (str_ends_with($file, '.meta')) {
                    continue;
                }

                $metadata = $this->getTempMeta($file);

                if ($metadata && isset($metadata['expires_at'])) {
                    $expiresAt = Carbon::parse($metadata['expires_at']);

                    if ($expiresAt->isPast()) {
                        if ($this->deleteTemp($file)) {
                            $cleaned[] = $file;
                        } else {
                            $errors[] = $file;
                        }
                    }
                } else {
                    // File without metadata or expired metadata, check file age
                    $fileTime = $tempDisk->lastModified($file);
                    $maxAge = ConfigHelper::getTempExpiryHours() * 3600;

                    if (time() - $fileTime > $maxAge) {
                        if ($this->deleteTemp($file)) {
                            $cleaned[] = $file;
                        } else {
                            $errors[] = $file;
                        }
                    }
                }
            }

            // Clean up empty chunk directories
            $chunkDirs = $tempDisk->directories('temp/chunks');
            foreach ($chunkDirs as $chunkDir) {
                $files = $tempDisk->allFiles($chunkDir);
                if (empty($files)) {
                    $tempDisk->deleteDirectory($chunkDir);
                }
            }
        } catch (\Exception $e) {
            Log::error('Temp file cleanup error: '.$e->getMessage());
            $errors[] = 'General cleanup error: '.$e->getMessage();
        }

        return [
            'cleaned' => $cleaned,
            'errors' => $errors,
            'cleaned_count' => count($cleaned),
            'error_count' => count($errors),
        ];
    }

    /**
     * Validate file extension against allowed types with caching
     */
    public function allowsExtension(string $extension): bool
    {
        if (self::$runtimeCaches['allowedExtensions'] === null) {
            self::$runtimeCaches['allowedExtensions'] = array_flip(
                ConfigHelper::getAllowedExtensions()
            );
        }

        return isset(self::$runtimeCaches['allowedExtensions'][strtolower($extension)]);
    }

    /**
     * Validate MIME type against allowed types with caching
     */
    public function allowsMimeType(string $mimeType): bool
    {
        if (self::$runtimeCaches['allowedMimeTypes'] === null) {
            self::$runtimeCaches['allowedMimeTypes'] = array_flip(
                ConfigHelper::getAllowedMimeTypes()
            );
        }

        return isset(self::$runtimeCaches['allowedMimeTypes'][$mimeType]);
    }

    /**
     * Memory-efficient file validation for large files
     */
    public function validateLargeFile(string $tempPath, string $originalName, int $chunkSize = 8192): array
    {
        try {
            $tempDisk = $this->getTempDisk();

            if (! $tempDisk->exists($tempPath)) {
                return ['valid' => false, 'message' => __('filex::translations.errors.file_not_found')];
            }

            $fileSize = $tempDisk->size($tempPath);
            $maxSize = ConfigHelper::getMaxFileSize();

            if ($fileSize > $maxSize) {
                return ['valid' => false, 'message' => __('filex::translations.errors.file_size_exceeds_limit')];
            }

            // Get file extension
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            if (! $this->allowsExtension($extension)) {
                return ['valid' => false, 'message' => __('filex::translations.errors.file_type_not_allowed')];
            }

            // For large files, use streaming validation
            if ($fileSize > 50 * 1024 * 1024) { // 50MB threshold
                return $this->validateStreamingFile($tempPath, $originalName, $chunkSize);
            }

            // Use regular validation for smaller files
            return $this->validateTemp($tempPath, $originalName);
        } catch (\Exception $e) {
            Log::error('Large file validation error: '.$e->getMessage(), ['temp_path' => $tempPath]);

            return ['valid' => false, 'message' => __('filex::translations.errors.file_validation_failed')];
        }
    }

    /**
     * Streaming file validation for very large files
     */
    protected function validateStreamingFile(string $tempPath, string $originalName, int $chunkSize): array
    {
        $tempDisk = $this->getTempDisk();
        $filePath = $tempDisk->path($tempPath);

        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return ['valid' => false, 'message' => __('filex::translations.errors.chunk_file_error')];
        }

        try {
            // Read first chunk for MIME detection
            $firstChunk = fread($handle, min($chunkSize, 1024));
            if ($firstChunk === false) {
                return ['valid' => false, 'message' => __('filex::translations.errors.chunk_file_error')];
            }

            // Basic MIME detection from first chunk
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $firstChunk);
            finfo_close($finfo);

            if (! $this->allowsMimeType($mimeType)) {
                return ['valid' => false, 'message' => __('filex::translations.errors.invalid_file_type_detected')];
            }

            return ['valid' => true, 'message' => __('filex::translations.validation')];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Get file icon class based on extension
     */
    public function getFileIcon(string $extension): string
    {
        $iconMap = [
            'pdf' => 'fa-file-pdf',
            'doc' => 'fa-file-word',
            'docx' => 'fa-file-word',
            'xls' => 'fa-file-excel',
            'xlsx' => 'fa-file-excel',
            'ppt' => 'fa-file-powerpoint',
            'pptx' => 'fa-file-powerpoint',
            'txt' => 'fa-file-text',
            'zip' => 'fa-file-archive',
            'rar' => 'fa-file-archive',
            '7z' => 'fa-file-archive',
            'jpg' => 'fa-file-image',
            'jpeg' => 'fa-file-image',
            'png' => 'fa-file-image',
            'gif' => 'fa-file-image',
            'bmp' => 'fa-file-image',
            'webp' => 'fa-file-image',
            'svg' => 'fa-file-image',
            'mp3' => 'fa-file-audio',
            'wav' => 'fa-file-audio',
            'ogg' => 'fa-file-audio',
            'mp4' => 'fa-file-video',
            'avi' => 'fa-file-video',
            'mov' => 'fa-file-video',
            'wmv' => 'fa-file-video',
            'flv' => 'fa-file-video',
        ];

        return $iconMap[strtolower($extension)] ?? 'fa-file';
    }

    /**
     * Stream copy a file from one disk to another to avoid memory issues
     */
    public function copyStream($sourceDisk, string $sourcePath, $targetDisk, string $targetPath): bool
    {
        try {
            // Check if both disks support streaming
            $sourceHandle = $sourceDisk->readStream($sourcePath);
            if (! $sourceHandle) {
                // Fallback to regular copy for disks that don't support streaming
                return $targetDisk->put($targetPath, $sourceDisk->get($sourcePath));
            }

            $success = $targetDisk->writeStream($targetPath, $sourceHandle);

            if (is_resource($sourceHandle)) {
                fclose($sourceHandle);
            }

            return $success !== false;
        } catch (\Exception $e) {
            Log::error('Stream file copy failed: '.$e->getMessage(), [
                'source' => $sourcePath,
                'target' => $targetPath,
            ]);

            // Fallback to regular copy
            try {
                return $targetDisk->put($targetPath, $sourceDisk->get($sourcePath));
            } catch (\Exception $fallbackError) {
                Log::error('Fallback file copy also failed: '.$fallbackError->getMessage());

                return false;
            }
        }
    }

    /**
     * Store an uploaded file using streaming to avoid memory issues
     */
    public function storeStream(UploadedFile $file, string $directory, ?string $disk = null, ?string $filename = null): string
    {
        $disk = $disk ?? ConfigHelper::getTempDisk();
        $diskInstance = Storage::disk($disk);

        if (! $filename) {
            $filename = $this->generateFileName($file->getClientOriginalName());
        }

        $path = trim($directory, '/').'/'.$filename;

        // Ensure directory exists
        $dir = dirname($path);
        if (! $diskInstance->exists($dir)) {
            $diskInstance->makeDirectory($dir);
        }

        // Use streaming to store the file
        $fileStream = fopen($file->getRealPath(), 'rb');
        if (! $fileStream) {
            throw new \RuntimeException(__('filex::translations.errors.chunk_file_error', ['file' => $file->getRealPath()]));
        }

        try {
            $success = $diskInstance->writeStream($path, $fileStream);
            if ($success === false) {
                throw new \RuntimeException(__('filex::translations.errors.output_file_error'));
            }
        } finally {
            fclose($fileStream);
        }

        return $path;
    }

    /**
     * Optimized streaming upload with better error handling
     */
    public function storeOptimized(UploadedFile $file, string $directory, ?string $disk = null, ?string $filename = null): string
    {
        $disk = $disk ?? ConfigHelper::getTempDisk();
        $diskInstance = Storage::disk($disk);

        if (! $filename) {
            $filename = $this->generateFileName($file->getClientOriginalName());
        }

        $path = trim($directory, '/').'/'.$filename;

        // Pre-create directory to avoid repeated checks
        $dir = dirname($path);
        if (! $diskInstance->exists($dir)) {
            $diskInstance->makeDirectory($dir);
        }

        // Use optimal buffer size based on file size
        $fileSize = $file->getSize();
        $bufferSize = $this->getBufferSize($fileSize);

        $sourceHandle = fopen($file->getRealPath(), 'rb');
        if (! $sourceHandle) {
            throw new \RuntimeException(__('filex::translations.errors.chunk_file_error', ['file' => $file->getRealPath()]));
        }

        $targetHandle = $diskInstance->writeStream($path, $sourceHandle);

        if ($targetHandle === false) {
            fclose($sourceHandle);
            throw new \RuntimeException(__('filex::translations.errors.output_file_error'));
        }

        return $path;
    }

    /**
     * Deferred validation - validate only critical aspects first
     */
    public function validateDeferred(string $tempPath, string $originalName): array
    {
        try {
            $tempDisk = $this->getTempDisk();

            if (! $tempDisk->exists($tempPath)) {
                return ['valid' => false, 'message' => __('filex::translations.errors.file_not_found')];
            }

            // Quick size check
            $fileSize = $tempDisk->size($tempPath);
            $maxSize = ConfigHelper::getMaxFileSize();

            if ($fileSize > $maxSize) {
                return ['valid' => false, 'message' => __('filex::translations.errors.file_size_exceeds_limit')];
            }

            // Defer expensive MIME validation if configured
            if (ConfigHelper::get('performance.defer_validation', true)) {
                return ['valid' => true, 'message' => __('filex::translations.validation')];
            }

            // Fall back to full validation
            return $this->validateTemp($tempPath, $originalName);
        } catch (\Exception $e) {
            Log::error('File validation error: '.$e->getMessage(), ['temp_path' => $tempPath]);

            return ['valid' => false, 'message' => __('filex::translations.errors.file_validation_failed')];
        }
    }

    /**
     * Detect real MIME type using finfo with caching
     */
    protected function detectRealMimeType(string $filePath): string
    {
        $cacheKey = md5_file($filePath);

        if (isset(self::$mimeTypeCache[$cacheKey])) {
            return self::$mimeTypeCache[$cacheKey];
        }

        // Check memory before expensive operation
        $this->checkMemoryUsage();

        $mimeType = null;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        try {
            $mimeType = finfo_file($finfo, $filePath);
        } finally {
            if ($finfo) {
                finfo_close($finfo);
            }
        }

        // Cache the result with size limit
        self::$mimeTypeCache[$cacheKey] = $mimeType;

        // Limit cache size to prevent memory leaks
        if (count(self::$mimeTypeCache) > self::MAX_CACHE_SIZE['mimeType']) {
            array_shift(self::$mimeTypeCache);
        }

        return $mimeType;
    }

    /**
     * Enhanced buffer size calculation with caching
     */
    protected function getBufferSize(int $fileSize): int
    {
        $cacheKey = $fileSize;

        if (isset(self::$bufferSizeCache[$cacheKey])) {
            return self::$bufferSizeCache[$cacheKey];
        }

        // Base buffer size on file size and available memory
        $memoryLimit = ByteHelper::convertToBytes(ini_get('memory_limit'));
        $currentUsage = memory_get_usage(true);
        $availableMemory = $memoryLimit - $currentUsage;

        // Calculate optimal buffer size
        if ($fileSize < 1024 * 1024) { // < 1MB
            $bufferSize = 8192; // 8KB
        } elseif ($fileSize < 10 * 1024 * 1024) { // < 10MB
            $bufferSize = 64 * 1024; // 64KB
        } elseif ($fileSize < 100 * 1024 * 1024) { // < 100MB
            $bufferSize = 256 * 1024; // 256KB
        } else {
            $bufferSize = 1024 * 1024; // 1MB
        }

        // Ensure buffer size doesn't exceed 10% of available memory
        $maxBuffer = (int) ($availableMemory * 0.1);
        $bufferSize = min($bufferSize, $maxBuffer);

        // Cache the result with size limit
        self::$bufferSizeCache[$cacheKey] = $bufferSize;

        // Limit cache size to prevent memory leaks
        if (count(self::$bufferSizeCache) > self::MAX_CACHE_SIZE['bufferSize']) {
            array_shift(self::$bufferSizeCache);
        }

        return $bufferSize;
    }

    /**
     * Batch process multiple files efficiently
     */
    public function moveBatch(array $tempPaths, string $targetDirectory, ?string $disk = null, ?string $visibility = null): array
    {
        $disk = $disk ?? ConfigHelper::getDefaultDisk();
        $visibility = $visibility ?? ConfigHelper::get('storage.visibility.default', 'public');
        $tempDisk = $this->getTempDisk();
        $batchSize = ConfigHelper::get('performance.batch_size', 5);
        $results = [];

        // Process in batches to manage memory
        $batches = array_chunk($tempPaths, $batchSize);

        foreach ($batches as $batch) {
            foreach ($batch as $tempPath) {
                try {
                    $result = $this->moveFile($tempPath, $targetDirectory, $disk, $tempDisk, $visibility);
                    $results[] = $result;
                } catch (\Exception $e) {
                    $results[] = [
                        'success' => false,
                        'tempPath' => $tempPath,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            // Force garbage collection between batches
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        return $results;
    }

    /**
     * Process a single file move operation
     */
    protected function moveFile(string $tempPath, string $targetDirectory, string $disk, $tempDisk, ?string $visibility = null): array
    {
        if (! str_starts_with($tempPath, 'temp/')) {
            return ['success' => false, 'tempPath' => $tempPath, 'message' => __('filex::translations.errors.invalid_file_path')];
        }

        if (! $tempDisk->exists($tempPath)) {
            return ['success' => false, 'tempPath' => $tempPath, 'message' => __('filex::translations.errors.file_not_found')];
        }

        $metadata = $this->getTempMeta($tempPath);
        $originalName = $metadata['original_name'] ?? basename($tempPath);

        $finalFileName = $this->generateFileName($originalName);
        $finalPath = trim($targetDirectory, '/').'/'.$finalFileName;

        // Ensure target directory exists
        $targetDisk = Storage::disk($disk);
        $targetDir = dirname($finalPath);
        if (! $targetDisk->exists($targetDir)) {
            $targetDisk->makeDirectory($targetDir);
        }

        // Copy file using streaming
        $moved = $this->copyStream($tempDisk, $tempPath, $targetDisk, $finalPath);

        if ($moved) {
            // Set file visibility if specified
            if ($visibility) {
                try {
                    $targetDisk->setVisibility($finalPath, $visibility);
                } catch (\Exception $e) {
                    // Log visibility error but don't fail the upload
                    Log::warning('Failed to set file visibility: '.$e->getMessage(), [
                        'file_path' => $finalPath,
                        'visibility' => $visibility,
                    ]);
                }
            }

            $this->deleteTemp($tempPath);

            return [
                'success' => true,
                'tempPath' => $tempPath,
                'finalPath' => $finalPath,
                'metadata' => $metadata,
            ];
        }

        return ['success' => false, 'tempPath' => $tempPath, 'message' => __('filex::translations.errors.file_validation_failed')];
    }

    /**
     * Enhanced file validation with multiple security layers
     */
    public function validateSecure(string $tempPath, string $originalName): array
    {
        try {
            // Check if suspicious detection is enabled
            if (! ConfigHelper::get('security.suspicious_detection.enabled', true)) {
                return $this->validateTemp($tempPath, $originalName);
            }

            $tempDisk = $this->getTempDisk();

            if (! $tempDisk->exists($tempPath)) {
                return ['valid' => false, 'message' => __('filex::translations.errors.file_not_found')];
            }

            $filePath = $tempDisk->path($tempPath);
            $fileSize = $tempDisk->size($tempPath);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            // 1. Basic validations
            $basicValidation = $this->validateTemp($tempPath, $originalName);
            if (! $basicValidation['valid']) {
                return $basicValidation;
            }

            // 2. File signature validation (if enabled)
            if (ConfigHelper::get('security.suspicious_detection.validate_signatures', true)) {
                if (! $this->validateFileSignature($filePath, $extension)) {
                    return ['valid' => false, 'message' => __('filex::translations.errors.file_signature_validation_failed')];
                }
            }

            // 3. Content-based validation for specific file types
            if (! $this->validateFileContent($filePath, $extension)) {
                return ['valid' => false, 'message' => __('filex::translations.errors.file_content_validation_failed')];
            }

            // 4. Security scanning (if enabled)
            if (ConfigHelper::get('security.suspicious_detection.scan_content', true)) {
                if (! $this->scanForThreats($filePath, $originalName)) {
                    return ['valid' => false, 'message' => __('filex::translations.errors.file_security_validation_failed')];
                }
            }

            return ['valid' => true, 'message' => __('filex::translations.validation')];
        } catch (\Exception $e) {
            Log::error('Enhanced file validation error: '.$e->getMessage(), [
                'temp_path' => $tempPath,
                'original_name' => $originalName,
            ]);

            return ['valid' => false, 'message' => __('filex::translations.errors.file_validation_failed')];
        }
    }

    /**
     * Validate file signature (magic bytes) with caching
     */
    protected function validateFileSignature(string $filePath, string $extension): bool
    {
        $cacheKey = $extension.'_'.md5_file($filePath);

        if (isset(self::$signatureCache[$cacheKey])) {
            return self::$signatureCache[$cacheKey];
        }

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return false;
        }

        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return false;
        }

        $header = fread($handle, 32);
        fclose($handle);

        if ($header === false) {
            return false;
        }

        $signatures = [
            'jpg' => ["\xFF\xD8\xFF"],
            'jpeg' => ["\xFF\xD8\xFF"],
            'png' => ["\x89PNG\r\n\x1A\n"],
            'gif' => ['GIF87a', 'GIF89a'],
            'pdf' => ['%PDF-'],
            'zip' => ["PK\x03\x04"],
            'docx' => ["PK\x03\x04"],
            'xlsx' => ["PK\x03\x04"],
            'pptx' => ["PK\x03\x04"],
        ];

        $result = true;
        if (isset($signatures[$extension])) {
            $result = false;
            foreach ($signatures[$extension] as $signature) {
                if (str_starts_with($header, $signature)) {
                    $result = true;
                    break;
                }
            }
        }

        // Cache the result with size limit
        self::$signatureCache[$cacheKey] = $result;
        if (count(self::$signatureCache) > self::MAX_CACHE_SIZE['signature']) {
            array_shift(self::$signatureCache);
        }

        return $result;
    }

    /**
     * Validate file content for specific types
     */
    protected function validateFileContent(string $filePath, string $extension): bool
    {
        try {
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                case 'webp':
                    return $this->validateImageContent($filePath);

                case 'pdf':
                    return $this->validatePdfContent($filePath);

                case 'docx':
                case 'xlsx':
                case 'pptx':
                    return $this->validateOfficeContent($filePath);

                default:
                    return true; // No specific validation for this type
            }
        } catch (\Exception $e) {
            Log::warning('Content validation error', [
                'file_path' => basename($filePath),
                'extension' => $extension,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate image content using getimagesize
     */
    protected function validateImageContent(string $filePath): bool
    {
        $imageInfo = @getimagesize($filePath);

        return $imageInfo !== false;
    }

    /**
     * Validate PDF content structure
     */
    protected function validatePdfContent(string $filePath): bool
    {
        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return false;
        }

        // Check PDF header
        $header = fread($handle, 8);
        if (! str_starts_with($header, '%PDF-')) {
            fclose($handle);

            return false;
        }

        // Check for PDF trailer (look for %%EOF)
        fseek($handle, -256, SEEK_END);
        $trailer = fread($handle, 256);
        fclose($handle);

        return str_contains($trailer, '%%EOF');
    }

    /**
     * Validate Office document content (ZIP-based OOXML)
     */
    protected function validateOfficeContent(string $filePath): bool
    {
        if (! class_exists('ZipArchive')) {
            return true; // Skip validation if ZipArchive not available
        }

        $zip = new \ZipArchive;
        $result = $zip->open($filePath, \ZipArchive::RDONLY);

        if ($result !== true) {
            return false;
        }

        // Check for required OOXML structure
        $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
        $hasRels = $zip->locateName('_rels/.rels') !== false;

        $zip->close();

        return $hasContentTypes && $hasRels;
    }

    /**
     * Scan for potential security threats
     */
    protected function scanForThreats(string $filePath, string $originalName): bool
    {
        // 1. Check for executable disguised as other files
        if ($this->isExecutableFile($filePath)) {
            Log::alert('Executable file detected', [
                'file_path' => basename($filePath),
                'original_name' => $originalName,
            ]);

            return false;
        }

        // 2. Check for suspicious file names
        if ($this->hasSuspiciousFileName($originalName)) {
            Log::alert('Suspicious filename detected', [
                'original_name' => $originalName,
            ]);

            return false;
        }

        // 3. Scan file content for suspicious patterns
        if ($this->containsSuspiciousContent($filePath)) {
            Log::alert('Suspicious content detected', [
                'file_path' => basename($filePath),
                'original_name' => $originalName,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Check if file is an executable
     */
    protected function isExecutableFile(string $filePath): bool
    {
        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        // Get executable signatures from config
        $executableSignatures = ConfigHelper::get('security.executable_signatures', [
            "\x4D\x5A",           // PE/EXE files
            "\x7FELF",            // ELF files
            "\xCF\xFA\xED\xFE",   // Mach-O files
            '#!/bin/',            // Shell scripts
            '#!/usr/bin/',        // Shell scripts
        ]);

        foreach ($executableSignatures as $signature) {
            if (str_starts_with($header, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for suspicious file names
     */
    protected function hasSuspiciousFileName(string $filename): bool
    {
        // Null bytes
        if (str_contains($filename, "\0")) {
            return true;
        }

        // Path traversal
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return true;
        }

        // Get suspicious patterns from config
        $suspiciousPatterns = ConfigHelper::get('security.suspicious_filename_patterns', [
            // Default patterns if config is not available
            '/\.[a-z]{2,4}\.[a-z]{2,4}$/i',
            '/\.(php|phtml|php3|php4|php5)$/i',
            '/\.(asp|aspx|jsp|cfm)$/i',
            '/\.(exe|bat|cmd|scr)$/i',
            '/\.(htaccess|htpasswd)$/i',
        ]);

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan file content for suspicious patterns with optimized memory usage
     */
    protected function containsSuspiciousContent(string $filePath): bool
    {
        // Only scan text-based files to avoid false positives
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $textExtensions = ConfigHelper::get('security.text_extensions_to_scan', [
            'txt',
            'html',
            'htm',
            'css',
            'js',
            'json',
            'xml',
            'csv',
        ]);

        if (! in_array($extension, $textExtensions)) {
            return false; // Skip binary files
        }

        // Use optimized streaming approach for better memory usage
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $content = fread($handle, 10240); // Read first 10KB
        fclose($handle);

        if ($content === false) {
            return false;
        }

        // Get suspicious patterns from config with caching
        if (self::$runtimeCaches['suspiciousPatterns'] === null) {
            self::$runtimeCaches['suspiciousPatterns'] = ConfigHelper::get('security.suspicious_content_patterns', [
                // Default patterns if config is not available
                '/<\?php/i',
                '/<%[^>]*%>/i',
                '/javascript:/i',
                '/vbscript:/i',
                '/onload\s*=/i',
                '/onerror\s*=/i',
                '/eval\s*\(/i',
                '/exec\s*\(/i',
                '/system\s*\(/i',
                '/shell_exec\s*\(/i',
                '/passthru\s*\(/i',
                '/base64_decode\s*\(/i',
            ]);
        }

        // Use optimized pattern matching with early exit
        foreach (self::$runtimeCaches['suspiciousPatterns'] as $pattern) {
            if (preg_match($pattern, $content)) {
                return true; // Early exit on first match
            }
        }

        return false;
    }

    /**
     * Quarantine suspicious file
     */
    public function quarantineFile(string $tempPath, string $reason): bool
    {
        try {
            // Check if quarantine is enabled
            if (! ConfigHelper::get('security.suspicious_detection.quarantine_enabled', true)) {
                return false;
            }

            $tempDisk = $this->getTempDisk();
            $quarantineBaseDir = ConfigHelper::get('security.quarantine.directory', 'quarantine');
            $quarantineDir = $quarantineBaseDir.'/'.date('Y/m/d');
            $quarantineFile = $quarantineDir.'/'.basename($tempPath).'_'.time();

            // Create quarantine directory
            if (! $tempDisk->exists($quarantineDir)) {
                $tempDisk->makeDirectory($quarantineDir);
            }

            // Move file to quarantine
            $result = $tempDisk->move($tempPath, $quarantineFile);

            if ($result) {
                // Create quarantine metadata
                $metadata = [
                    'original_path' => $tempPath,
                    'quarantined_at' => now()->toISOString(),
                    'reason' => $reason,
                    'user_id' => Auth::id(),
                    'ip_address' => request()->ip(),
                ];

                $tempDisk->put($quarantineFile.'.meta', json_encode($metadata));

                Log::alert('File quarantined', [
                    'original_path' => $tempPath,
                    'quarantine_path' => $quarantineFile,
                    'reason' => $reason,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to quarantine file: '.$e->getMessage(), [
                'temp_path' => $tempPath,
                'reason' => $reason,
            ]);

            return false;
        }
    }

    /**
     * Check if suspicious detection is enabled
     */
    public function isSuspiciousDetectionEnabled(): bool
    {
        return ConfigHelper::get('security.suspicious_detection.enabled', true);
    }

    /**
     * Clean up quarantined files based on retention policy
     */
    public function cleanupQuarantine(): array
    {
        $tempDisk = $this->getTempDisk();
        $quarantineBaseDir = ConfigHelper::get('security.quarantine.directory', 'quarantine');
        $retentionDays = ConfigHelper::get('security.quarantine.retention_days', 30);
        $autoCleanup = ConfigHelper::get('security.quarantine.auto_cleanup', true);

        $cleaned = [];
        $errors = [];

        if (! $autoCleanup) {
            return [
                'cleaned' => $cleaned,
                'errors' => ['Quarantine auto-cleanup is disabled'],
                'cleaned_count' => 0,
                'error_count' => 1,
            ];
        }

        try {
            $quarantineFiles = $tempDisk->allFiles($quarantineBaseDir);
            $cutoffTime = now()->subDays($retentionDays);

            foreach ($quarantineFiles as $file) {
                // Skip metadata files, they'll be handled with their parent files
                if (str_ends_with($file, '.meta')) {
                    continue;
                }

                try {
                    $metadataPath = $file.'.meta';

                    if ($tempDisk->exists($metadataPath)) {
                        $metadata = json_decode($tempDisk->get($metadataPath), true);

                        if (isset($metadata['quarantined_at'])) {
                            $quarantinedAt = Carbon::parse($metadata['quarantined_at']);

                            if ($quarantinedAt->lt($cutoffTime)) {
                                // Delete both file and metadata
                                if ($tempDisk->delete($file) && $tempDisk->delete($metadataPath)) {
                                    $cleaned[] = $file;
                                } else {
                                    $errors[] = $file;
                                }
                            }
                        }
                    } else {
                        // File without metadata, check file age
                        $fileTime = $tempDisk->lastModified($file);

                        if ($fileTime && $fileTime < $cutoffTime->timestamp) {
                            if ($tempDisk->delete($file)) {
                                $cleaned[] = $file;
                            } else {
                                $errors[] = $file;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error cleaning quarantine file: '.$e->getMessage(), ['file' => $file]);
                    $errors[] = $file;
                }
            }

            // Clean up empty quarantine directories
            $quarantineDirs = $tempDisk->directories($quarantineBaseDir);
            foreach ($quarantineDirs as $dir) {
                $files = $tempDisk->allFiles($dir);
                if (empty($files)) {
                    $tempDisk->deleteDirectory($dir);
                }
            }
        } catch (\Exception $e) {
            Log::error('Quarantine cleanup error: '.$e->getMessage());
            $errors[] = 'General cleanup error: '.$e->getMessage();
        }

        return [
            'cleaned' => $cleaned,
            'errors' => $errors,
            'cleaned_count' => count($cleaned),
            'error_count' => count($errors),
        ];
    }

    /**
     * Bulk file operations with enhanced error handling and performance monitoring
     */
    public function bulkFileOperations(array $operations): array
    {
        $startTime = microtime(true);
        PerformanceMonitor::startTimer('bulk_operations');

        $results = [
            'completed' => [],
            'failed' => [],
            'metrics' => [],
        ];

        $batchSize = ConfigHelper::get('performance.bulk_batch_size', 10);
        $batches = array_chunk($operations, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            PerformanceMonitor::startTimer("bulk_batch_{$batchIndex}");

            foreach ($batch as $operation) {
                try {
                    $result = $this->executeFileOperation($operation);
                    if ($result['success']) {
                        $results['completed'][] = $result;
                    } else {
                        $results['failed'][] = $result;
                    }
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'operation' => $operation,
                        'error' => $e->getMessage(),
                        'success' => false,
                    ];
                }
            }

            // Monitor memory and optimize if needed
            $this->checkMemoryUsage();
            if (memory_get_usage(true) > ByteHelper::convertToBytes(ini_get('memory_limit')) * 0.8) {
                $this->optimizeCaches();
            }

            PerformanceMonitor::endTimer("bulk_batch_{$batchIndex}");
        }

        $endTime = microtime(true);
        $results['metrics'] = [
            'total_time' => $endTime - $startTime,
            'total_operations' => count($operations),
            'completed_count' => count($results['completed']),
            'failed_count' => count($results['failed']),
            'memory_peak' => memory_get_peak_usage(true),
            'batches_processed' => count($batches),
        ];

        PerformanceMonitor::endTimer('bulk_operations', $results['metrics']);

        return $results;
    }

    /**
     * Execute a single file operation based on type
     */
    protected function executeFileOperation(array $operation): array
    {
        $type = $operation['type'] ?? 'unknown';

        switch ($type) {
            case 'move':
                return $this->executeMoveOperation($operation);
            case 'validate':
                return $this->executeValidateOperation($operation);
            case 'cleanup':
                return $this->executeCleanupOperation($operation);
            default:
                return [
                    'success' => false,
                    'operation' => $operation,
                    'error' => 'Unknown operation type: '.$type,
                ];
        }
    }

    /**
     * Execute move operation
     */
    protected function executeMoveOperation(array $operation): array
    {
        $tempPath = $operation['tempPath'] ?? '';
        $targetDirectory = $operation['targetDirectory'] ?? '';
        $disk = $operation['disk'] ?? null;

        if (empty($tempPath) || empty($targetDirectory)) {
            return [
                'success' => false,
                'operation' => $operation,
                'error' => 'Missing required parameters for move operation',
            ];
        }

        $visibility = $operation['visibility'] ?? ConfigHelper::get('storage.visibility.default', 'public');

        $result = $this->moveFile($tempPath, $targetDirectory, $disk ?? ConfigHelper::getDefaultDisk(), $this->getTempDisk(), $visibility);

        return array_merge($result, ['operation' => $operation]);
    }

    /**
     * Execute validation operation
     */
    protected function executeValidateOperation(array $operation): array
    {
        $tempPath = $operation['tempPath'] ?? '';
        $originalName = $operation['originalName'] ?? '';
        $useSecure = $operation['secure'] ?? false;

        if (empty($tempPath) || empty($originalName)) {
            return [
                'success' => false,
                'operation' => $operation,
                'error' => 'Missing required parameters for validate operation',
            ];
        }

        if ($useSecure) {
            $result = $this->validateSecure($tempPath, $originalName);
        } else {
            $result = $this->validateTemp($tempPath, $originalName);
        }

        return [
            'success' => $result['valid'],
            'operation' => $operation,
            'message' => $result['message'],
        ];
    }

    /**
     * Execute cleanup operation
     */
    protected function executeCleanupOperation(array $operation): array
    {
        $type = $operation['cleanupType'] ?? 'temp';

        switch ($type) {
            case 'temp':
                $result = $this->cleanup();
                break;
            case 'quarantine':
                $result = $this->cleanupQuarantine();
                break;
            default:
                return [
                    'success' => false,
                    'operation' => $operation,
                    'error' => 'Unknown cleanup type: '.$type,
                ];
        }

        return [
            'success' => $result['error_count'] === 0,
            'operation' => $operation,
            'cleaned_count' => $result['cleaned_count'],
            'error_count' => $result['error_count'],
        ];
    }

    /**
     * Get file size in human readable format
     */
    public function formatSize(int $bytes): string
    {
        return ByteHelper::formatBytes($bytes);
    }

    /**
     * Get the default storage disk instance with caching
     */
    public function getDefaultDisk()
    {
        if (self::$runtimeCaches['defaultDisk'] === null) {
            self::$runtimeCaches['defaultDisk'] = Storage::disk(ConfigHelper::getDefaultDisk());
        }

        return self::$runtimeCaches['defaultDisk'];
    }

    /**
     * Get the temporary storage disk instance with caching
     */
    public function getTempDisk()
    {
        if (self::$runtimeCaches['tempDisk'] === null) {
            self::$runtimeCaches['tempDisk'] = Storage::disk(ConfigHelper::getTempDisk());
        }

        return self::$runtimeCaches['tempDisk'];
    }

    /**
     * Render Filex assets and routes configuration
     *
     * @return string
     */
    public function renderFilexAssetsAndRoutes()
    {
        $cssAssets = [
            asset('vendor/filex/css/dropzone.min.css'),
            asset('vendor/filex/css/filex.css'),
        ];

        $jsAssets = [
            asset('vendor/filex/js/dropzone.min.js'),
            asset('vendor/filex/js/filex.js'),
        ];

        $output = '';

        // Add CSS assets
        foreach ($cssAssets as $css) {
            $output .= '<link rel="stylesheet" href="'.$css.'" />'."\n";
        }

        // Add JS assets
        foreach ($jsAssets as $js) {
            $output .= '<script src="'.$js.'"></script>'."\n";
        }

        // Add routes configuration
        $uploadRoute = route('filex.upload.temp');
        $deleteRoute = route('filex.temp.delete', ['filename' => '__FILENAME__']);

        $output .= '<script>'."\n";
        $output .= 'window.filexRoutes = {'."\n";
        $output .= '    upload: "'.$uploadRoute.'",'."\n";
        $output .= '    delete: "'.$deleteRoute.'"'."\n";
        $output .= '};'."\n";
        $output .= '</script>'."\n";

        return $output;
    }

    /**
     * Static version of renderFilexAssetsAndRoutes to avoid dependency injection issues
     *
     * @return string
     */
    public static function renderFilexAssetsAndRoutesStatic()
    {
        $cssAssets = [
            asset('vendor/filex/css/dropzone.min.css'),
            asset('vendor/filex/css/filex.css'),
        ];

        $jsAssets = [
            asset('vendor/filex/js/dropzone.min.js'),
            asset('vendor/filex/js/filex.js'),
        ];

        $output = '';

        // Add CSS assets
        foreach ($cssAssets as $css) {
            $output .= '<link rel="stylesheet" href="'.$css.'" />'."\n";
        }

        // Add JS assets
        foreach ($jsAssets as $js) {
            $output .= '<script src="'.$js.'"></script>'."\n";
        }

        // Add routes configuration
        $uploadRoute = route('filex.upload.temp');
        $deleteRoute = route('filex.temp.delete', ['filename' => '__FILENAME__']);

        $output .= '<script>'."\n";
        $output .= 'window.filexRoutes = {'."\n";
        $output .= '    upload: "'.$uploadRoute.'",'."\n";
        $output .= '    delete: "'.$deleteRoute.'"'."\n";
        $output .= '};'."\n";
        $output .= '</script>'."\n";

        return $output;
    }

    /**
     * Render Filex assets (CSS and JS)
     *
     * @return string
     */
    public function renderFilexAssets()
    {
        $cssAssets = [
            asset('vendor/filex/css/dropzone.min.css'),
            asset('vendor/filex/css/filex.css'),
        ];

        $jsAssets = [
            asset('vendor/filex/js/dropzone.min.js'),
            asset('vendor/filex/js/filex.js'),
        ];

        $output = '';

        // Add CSS assets
        foreach ($cssAssets as $css) {
            $output .= '<link rel="stylesheet" href="'.$css.'" />'."\n";
        }

        // Add JS assets
        foreach ($jsAssets as $js) {
            $output .= '<script src="'.$js.'"></script>'."\n";
        }

        return $output;
    }

    /**
     * Render Filex routes configuration
     *
     * @param  string  $uploadRoute
     * @param  string  $deleteRoute
     * @return string
     */
    public function renderFilexRoutes($uploadRoute, $deleteRoute)
    {
        $output = '<script>'."\n";
        $output .= 'window.filexRoutes = {'."\n";
        $output .= '    upload: "'.$uploadRoute.'",'."\n";
        $output .= '    delete: "'.$deleteRoute.'"'."\n";
        $output .= '};'."\n";
        $output .= '</script>'."\n";

        return $output;
    }

    /**
     * Optimized bulk file move with batching and performance monitoring
     */
    public function moveFilesBulk(array $tempPaths, string $targetDirectory, ?string $disk = null, ?string $visibility = null): array
    {
        PerformanceMonitor::startTimer('bulk_file_move');
        PerformanceMonitor::checkMemoryUsage('Before bulk file move');

        $disk = $disk ?? ConfigHelper::getDefaultDisk();
        $visibility = $visibility ?? ConfigHelper::get('storage.visibility.default', 'public');
        $batchSize = ConfigHelper::get('performance.batch_size', 5);
        $tempDisk = $this->getTempDisk();
        $targetDisk = Storage::disk($disk);
        $results = [];

        // Pre-validate all paths for better error handling
        $validPaths = $this->preValidatePaths($tempPaths, $tempDisk);

        if (empty($validPaths)) {
            PerformanceMonitor::endTimer('bulk_file_move', ['result' => 'no_valid_paths']);

            return $results;
        }

        // Ensure target directory exists once
        $targetDir = trim($targetDirectory, '/');
        if (! $targetDisk->exists($targetDir)) {
            $targetDisk->makeDirectory($targetDir);
        }

        // Process files in batches for better performance
        $batches = array_chunk($validPaths, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            PerformanceMonitor::startTimer("batch_process_{$batchIndex}");
            $batchResults = $this->processMoveFileBatch($batch, $targetDirectory, $tempDisk, $targetDisk, $visibility);
            $results = array_merge($results, $batchResults);
            PerformanceMonitor::endTimer("batch_process_{$batchIndex}", [
                'batch_size' => count($batch),
                'successful' => count(array_filter($batchResults, fn ($r) => $r['success'])),
            ]);
        }

        PerformanceMonitor::checkMemoryUsage('After bulk file move');
        PerformanceMonitor::endTimer('bulk_file_move', [
            'total_files' => count($tempPaths),
            'valid_files' => count($validPaths),
            'batches' => count($batches),
            'successful' => count(array_filter($results, fn ($r) => $r['success'])),
        ]);

        return $results;
    }

    /**
     * Pre-validate temp paths for bulk operations
     */
    private function preValidatePaths(array $tempPaths, $tempDisk): array
    {
        $validPaths = [];

        foreach ($tempPaths as $tempPath) {
            if (str_starts_with($tempPath, 'temp/') && $tempDisk->exists($tempPath)) {
                $validPaths[] = $tempPath;
            }
        }

        return $validPaths;
    }

    /**
     * Process a batch of file moves with memory management
     */
    private function processMoveFileBatch(array $batch, string $targetDirectory, $tempDisk, $targetDisk, ?string $visibility = null): array
    {
        $results = [];
        $memoryLimit = ByteHelper::convertToBytes(ini_get('memory_limit'));
        $memoryThreshold = $memoryLimit * 0.8; // 80% of memory limit

        foreach ($batch as $tempPath) {
            try {
                // Check memory usage before processing each file
                if (memory_get_usage(true) > $memoryThreshold) {
                    // Force garbage collection
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }

                    // If still above threshold, delay processing
                    if (memory_get_usage(true) > $memoryThreshold) {
                        $results[] = [
                            'success' => false,
                            'tempPath' => $tempPath,
                            'message' => 'Insufficient memory available. Please try again later.',
                        ];

                        continue;
                    }
                }

                $metadata = $this->getTempMeta($tempPath);
                $originalName = $metadata['original_name'] ?? basename($tempPath);

                // Generate final filename
                $finalFileName = $this->generateFileName($originalName);
                $finalPath = trim($targetDirectory, '/').'/'.$finalFileName;

                // Use streaming with optimal buffer size
                $fileSize = $tempDisk->size($tempPath);
                $bufferSize = $this->getBufferSize($fileSize);

                $sourceHandle = $tempDisk->readStream($tempPath);
                if (! $sourceHandle) {
                    throw new \RuntimeException('Could not open source file for reading');
                }

                try {
                    $targetHandle = $targetDisk->writeStream($finalPath, $sourceHandle);

                    if ($targetHandle === false) {
                        throw new \RuntimeException('Could not write to target file');
                    }

                    // Set file visibility if specified
                    if ($visibility) {
                        try {
                            $targetDisk->setVisibility($finalPath, $visibility);
                        } catch (\Exception $e) {
                            // Log visibility error but don't fail the upload
                            Log::warning('Failed to set file visibility in batch: '.$e->getMessage(), [
                                'file_path' => $finalPath,
                                'visibility' => $visibility,
                            ]);
                        }
                    }

                    // Clean up temp file
                    $this->deleteTemp($tempPath);

                    $results[] = [
                        'success' => true,
                        'tempPath' => $tempPath,
                        'finalPath' => $finalPath,
                        'visibility' => $visibility,
                        'metadata' => $metadata,
                    ];
                } finally {
                    if (is_resource($sourceHandle)) {
                        fclose($sourceHandle);
                    }
                }

                // Release memory after each file
                unset($metadata);
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            } catch (\Exception $e) {
                Log::error('Failed to move temp file in batch', [
                    'temp_path' => $tempPath,
                    'error' => $e->getMessage(),
                    'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                ]);

                $results[] = [
                    'success' => false,
                    'tempPath' => $tempPath,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Enhanced byte formatting with caching
     */
    private function formatBytes(int $bytes): string
    {
        $cacheKey = $bytes;

        if (isset(self::$byteFormatCache[$cacheKey])) {
            return self::$byteFormatCache[$cacheKey];
        }

        $result = ByteHelper::formatBytes($bytes);

        // Cache the result with size limit
        self::$byteFormatCache[$cacheKey] = $result;

        // Limit cache size to prevent memory leaks
        if (count(self::$byteFormatCache) > self::MAX_CACHE_SIZE['byteFormat']) {
            array_shift(self::$byteFormatCache);
        }

        return $result;
    }

    /**
     * Initialize memory thresholds
     */
    private function initMemoryThresholds(): void
    {
        if (self::$memoryThresholds === null) {
            $limit = ByteHelper::convertToBytes(ini_get('memory_limit'));
            self::$memoryThresholds = [
                'warning' => $limit * 0.75,  // 75% of memory limit
                'critical' => $limit * 0.85,  // 85% of memory limit
                'emergency' => $limit * 0.95, // 95% of memory limit
            ];
        }
    }

    /**
     * Check memory usage and take action if needed
     */
    private function checkMemoryUsage(): void
    {
        $this->initMemoryThresholds();
        $currentUsage = memory_get_usage(true);

        // Update high watermark
        if (self::$memoryHighWatermark === null || $currentUsage > self::$memoryHighWatermark) {
            self::$memoryHighWatermark = $currentUsage;
        }

        if ($currentUsage >= self::$memoryThresholds['emergency']) {
            // Emergency: Force garbage collection and clear caches
            gc_collect_cycles();
            self::$mimeTypeCache = [];
            self::$bufferSizeCache = [];
            self::$byteFormatCache = [];
            Log::warning('Emergency memory cleanup performed', [
                'usage' => $this->formatBytes($currentUsage),
                'limit' => $this->formatBytes(ByteHelper::convertToBytes(ini_get('memory_limit'))),
            ]);
        } elseif ($currentUsage >= self::$memoryThresholds['critical']) {
            // Critical: Trigger garbage collection
            gc_collect_cycles();
            Log::info('Memory usage critical, garbage collection triggered', [
                'usage' => $this->formatBytes($currentUsage),
            ]);
        } elseif ($currentUsage >= self::$memoryThresholds['warning']) {
            // Warning: Log for monitoring
            Log::info('High memory usage detected', [
                'usage' => $this->formatBytes($currentUsage),
            ]);
        }
    }

    /**
     * Log performance metrics for monitoring
     */
    public function logPerformanceMetrics(): array
    {
        $metrics = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ByteHelper::convertToBytes(ini_get('memory_limit')),
            'cache_sizes' => [
                'config' => count(self::$configCache),
                'mimeType' => count(self::$mimeTypeCache),
                'bufferSize' => count(self::$bufferSizeCache),
                'byteFormat' => count(self::$byteFormatCache),
                'signature' => count(self::$signatureCache),
            ],
            'memory_watermark' => self::$memoryHighWatermark,
            'timestamp' => now()->toISOString(),
        ];

        // Log if memory usage is concerning
        $memoryUsagePercent = ($metrics['memory_usage'] / $metrics['memory_limit']) * 100;
        if ($memoryUsagePercent > 75) {
            Log::warning('High memory usage detected in FilexService', [
                'usage_percent' => round($memoryUsagePercent, 2),
                'usage_mb' => round($metrics['memory_usage'] / 1024 / 1024, 2),
                'limit_mb' => round($metrics['memory_limit'] / 1024 / 1024, 2),
                'cache_sizes' => $metrics['cache_sizes'],
            ]);
        }

        return $metrics;
    }

    /**
     * Optimize cache sizes and clean up if needed
     */
    public function optimizeCaches(): array
    {
        $before = [
            'mimeType' => count(self::$mimeTypeCache),
            'bufferSize' => count(self::$bufferSizeCache),
            'byteFormat' => count(self::$byteFormatCache),
            'signature' => count(self::$signatureCache),
        ];

        // Clear caches that exceed optimal sizes
        if (count(self::$mimeTypeCache) > (int) (self::MAX_CACHE_SIZE['mimeType'] * 0.8)) {
            self::$mimeTypeCache = array_slice(self::$mimeTypeCache, -(int) (self::MAX_CACHE_SIZE['mimeType'] * 0.6), null, true);
        }

        if (count(self::$bufferSizeCache) > (int) (self::MAX_CACHE_SIZE['bufferSize'] * 0.8)) {
            self::$bufferSizeCache = array_slice(self::$bufferSizeCache, -(int) (self::MAX_CACHE_SIZE['bufferSize'] * 0.6), null, true);
        }

        if (count(self::$byteFormatCache) > (int) (self::MAX_CACHE_SIZE['byteFormat'] * 0.8)) {
            self::$byteFormatCache = array_slice(self::$byteFormatCache, -(int) (self::MAX_CACHE_SIZE['byteFormat'] * 0.6), null, true);
        }

        if (count(self::$signatureCache) > (int) (self::MAX_CACHE_SIZE['signature'] * 0.8)) {
            self::$signatureCache = array_slice(self::$signatureCache, -(int) (self::MAX_CACHE_SIZE['signature'] * 0.6), null, true);
        }

        $after = [
            'mimeType' => count(self::$mimeTypeCache),
            'bufferSize' => count(self::$bufferSizeCache),
            'byteFormat' => count(self::$byteFormatCache),
            'signature' => count(self::$signatureCache),
        ];

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        return [
            'before' => $before,
            'after' => $after,
            'memory_freed' => memory_get_usage(true),
        ];
    }
}
