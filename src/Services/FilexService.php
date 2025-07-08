<?php

namespace DevWizard\Filex\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

class FilexService
{
    /**
     * Generate a unique filename
     */
    public function generateFileName(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);
        
        return Str::slug($name) . '_' . $timestamp . '_' . $random . '.' . $extension;
    }

    /**
     * Validate temporary file
     */
    public function validateTemp(string $tempPath, string $originalName): array
    {
        try {
            $tempDisk = $this->getTempDisk();
            
            if (!$tempDisk->exists($tempPath)) {
                return ['valid' => false, 'message' => 'File not found'];
            }

            $fileSize = $tempDisk->size($tempPath);
            $maxSize = config('filex.max_file_size', 10) * 1024 * 1024; // Convert MB to bytes

            if ($fileSize > $maxSize) {
                return ['valid' => false, 'message' => 'File size exceeds maximum allowed size'];
            }

            // Get file extension
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExtensions = config('filex.allowed_extensions', []);

            if (!in_array($extension, $allowedExtensions)) {
                return ['valid' => false, 'message' => 'File type not allowed'];
            }

            // Additional MIME type validation for security
            $tempFilePath = $tempDisk->path($tempPath);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tempFilePath);
            finfo_close($finfo);

            $allowedMimeTypes = config('filex.allowed_mime_types', []);

            if (!in_array($mimeType, $allowedMimeTypes)) {
                return ['valid' => false, 'message' => 'Invalid file type detected'];
            }

            return ['valid' => true, 'message' => 'File is valid'];

        } catch (\Exception $e) {
            Log::error('File validation error: ' . $e->getMessage(), ['temp_path' => $tempPath]);
            return ['valid' => false, 'message' => 'File validation failed'];
        }
    }

    /**
     * Mark temporary file with metadata
     */
    public function markTemp(string $tempPath, array $metadata): bool
    {
        try {
            $tempDisk = $this->getTempDisk();
            $metadataPath = $tempPath . '.meta';
            $metadataContent = json_encode(array_merge($metadata, [
                'created_at' => now()->toISOString(),
                'expires_at' => now()->addHours(config('filex.temp_expiry_hours', 24))->toISOString()
            ]));

            return $tempDisk->put($metadataPath, $metadataContent);
        } catch (\Exception $e) {
            Log::error('Failed to mark temp file: ' . $e->getMessage(), ['temp_path' => $tempPath]);
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
            $metadataPath = $tempPath . '.meta';
            if (!$tempDisk->exists($metadataPath)) {
                return null;
            }

            $content = $tempDisk->get($metadataPath);
            return json_decode($content, true);
        } catch (\Exception $e) {
            Log::error('Failed to get temp file metadata: ' . $e->getMessage(), ['temp_path' => $tempPath]);
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

            $metadataPath = $tempPath . '.meta';
            if ($tempDisk->exists($metadataPath)) {
                $tempDisk->delete($metadataPath);
            }

            return $deleted;
        } catch (\Exception $e) {
            Log::error('Failed to delete temp file: ' . $e->getMessage(), ['temp_path' => $tempPath]);
            return false;
        }
    }

    /**
     * Move files from temp to permanent location
     */
    public function moveFiles(array $tempPaths, string $targetDirectory, ?string $disk = null): array
    {
        $disk = $disk ?? config('filex.default_disk', 'public');
        $tempDisk = $this->getTempDisk();
        $results = [];

        foreach ($tempPaths as $tempPath) {
            try {
                if (!str_starts_with($tempPath, 'temp/')) {
                    $results[] = ['success' => false, 'tempPath' => $tempPath, 'message' => 'Invalid temp path'];
                    continue;
                }

                if (!$tempDisk->exists($tempPath)) {
                    $results[] = ['success' => false, 'tempPath' => $tempPath, 'message' => 'Temp file not found'];
                    continue;
                }

                $metadata = $this->getTempMeta($tempPath);
                $originalName = $metadata['original_name'] ?? basename($tempPath);
                
                // Generate final filename
                $finalFileName = $this->generateFileName($originalName);
                $finalPath = trim($targetDirectory, '/') . '/' . $finalFileName;

                // Ensure target directory exists
                $targetDir = dirname($finalPath);
                if (!Storage::disk($disk)->exists($targetDir)) {
                    Storage::disk($disk)->makeDirectory($targetDir);
                }

                // Copy file to final location using streaming to avoid memory issues
                $moved = $this->copyStream($tempDisk, $tempPath, Storage::disk($disk), $finalPath);

                if ($moved) {
                    // Clean up temp file
                    $this->deleteTemp($tempPath);
                    
                    $results[] = [
                        'success' => true,
                        'tempPath' => $tempPath,
                        'finalPath' => $finalPath,
                        'url' => Storage::disk($disk)->url($finalPath),
                        'metadata' => $metadata
                    ];
                } else {
                    $results[] = ['success' => false, 'tempPath' => $tempPath, 'message' => 'Failed to move file'];
                }

            } catch (\Exception $e) {
                Log::error('Failed to move temp file: ' . $e->getMessage(), ['temp_path' => $tempPath]);
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
                    $maxAge = config('filex.temp_expiry_hours', 24) * 3600;
                    
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
            Log::error('Temp file cleanup error: ' . $e->getMessage());
            $errors[] = 'General cleanup error: ' . $e->getMessage();
        }

        return [
            'cleaned' => $cleaned,
            'errors' => $errors,
            'cleaned_count' => count($cleaned),
            'error_count' => count($errors)
        ];
    }

    /**
     * Get file size in human readable format
     */
    public function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Validate file extension against allowed types
     */
    public function allowsExtension(string $extension): bool
    {
        $allowedExtensions = config('filex.allowed_extensions', []);
        return in_array(strtolower($extension), $allowedExtensions);
    }

    /**
     * Validate MIME type against allowed types
     */
    public function allowsMimeType(string $mimeType): bool
    {
        $allowedMimeTypes = config('filex.allowed_mime_types', []);
        return in_array($mimeType, $allowedMimeTypes);
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
            if (!$sourceHandle) {
                // Fallback to regular copy for disks that don't support streaming
                return $targetDisk->put($targetPath, $sourceDisk->get($sourcePath));
            }

            $success = $targetDisk->writeStream($targetPath, $sourceHandle);
            
            if (is_resource($sourceHandle)) {
                fclose($sourceHandle);
            }
            
            return $success !== false;
        } catch (\Exception $e) {
            Log::error('Stream file copy failed: ' . $e->getMessage(), [
                'source' => $sourcePath,
                'target' => $targetPath
            ]);
            
            // Fallback to regular copy
            try {
                return $targetDisk->put($targetPath, $sourceDisk->get($sourcePath));
            } catch (\Exception $fallbackError) {
                Log::error('Fallback file copy also failed: ' . $fallbackError->getMessage());
                return false;
            }
        }
    }

    /**
     * Store an uploaded file using streaming to avoid memory issues
     */
    public function storeStream(UploadedFile $file, string $directory, ?string $disk = null, ?string $filename = null): string
    {
        $disk = $disk ?? config('filex.temp_disk', 'local');
        $diskInstance = Storage::disk($disk);
        
        if (!$filename) {
            $filename = $this->generateFileName($file->getClientOriginalName());
        }
        
        $path = trim($directory, '/') . '/' . $filename;
        
        // Ensure directory exists
        $dir = dirname($path);
        if (!$diskInstance->exists($dir)) {
            $diskInstance->makeDirectory($dir);
        }
        
        // Use streaming to store the file
        $fileStream = fopen($file->getRealPath(), 'rb');
        if (!$fileStream) {
            throw new \RuntimeException('Could not open uploaded file for reading');
        }
        
        try {
            $success = $diskInstance->writeStream($path, $fileStream);
            if ($success === false) {
                throw new \RuntimeException('Failed to write uploaded file to storage');
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
        $disk = $disk ?? config('filex.temp_disk', 'local');
        $diskInstance = Storage::disk($disk);
        
        if (!$filename) {
            $filename = $this->generateFileName($file->getClientOriginalName());
        }
        
        $path = trim($directory, '/') . '/' . $filename;
        
        // Pre-create directory to avoid repeated checks
        $dir = dirname($path);
        if (!$diskInstance->exists($dir)) {
            $diskInstance->makeDirectory($dir);
        }
        
        // Use optimal buffer size based on file size
        $fileSize = $file->getSize();
        $bufferSize = $this->getBufferSize($fileSize);
        
        $sourceHandle = fopen($file->getRealPath(), 'rb');
        if (!$sourceHandle) {
            throw new \RuntimeException('Could not open uploaded file for reading');
        }
        
        $targetHandle = $diskInstance->writeStream($path, $sourceHandle);
        
        if ($targetHandle === false) {
            fclose($sourceHandle);
            throw new \RuntimeException('Failed to write uploaded file to storage');
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
            
            if (!$tempDisk->exists($tempPath)) {
                return ['valid' => false, 'message' => 'File not found'];
            }

            // Quick size check
            $fileSize = $tempDisk->size($tempPath);
            $maxSize = config('filex.max_file_size', 10) * 1024 * 1024;

            if ($fileSize > $maxSize) {
                return ['valid' => false, 'message' => 'File size exceeds maximum allowed size'];
            }

            // Defer expensive MIME validation if configured
            if (config('filex.performance.defer_validation', true)) {
                return ['valid' => true, 'message' => 'File is valid (deferred validation)'];
            }

            // Fall back to full validation
            return $this->validateTemp($tempPath, $originalName);

        } catch (\Exception $e) {
            Log::error('File validation error: ' . $e->getMessage(), ['temp_path' => $tempPath]);
            return ['valid' => false, 'message' => 'File validation failed'];
        }
    }

    /**
     * Get optimal buffer size based on file size
     */
    protected function getBufferSize(int $fileSize): int
    {
        // Dynamic buffer sizing for better performance
        if ($fileSize < 1024 * 1024) { // < 1MB
            return 4096; // 4KB
        } elseif ($fileSize < 10 * 1024 * 1024) { // < 10MB
            return 8192; // 8KB
        } elseif ($fileSize < 100 * 1024 * 1024) { // < 100MB
            return 16384; // 16KB
        } else {
            return 32768; // 32KB for very large files
        }
    }

    /**
     * Batch process multiple files efficiently
     */
    public function moveBatch(array $tempPaths, string $targetDirectory, ?string $disk = null): array
    {
        $disk = $disk ?? config('filex.default_disk', 'public');
        $tempDisk = $this->getTempDisk();
        $batchSize = config('filex.performance.batch_size', 5);
        $results = [];

        // Process in batches to manage memory
        $batches = array_chunk($tempPaths, $batchSize);
        
        foreach ($batches as $batch) {
            foreach ($batch as $tempPath) {
                try {
                    $result = $this->moveFile($tempPath, $targetDirectory, $disk, $tempDisk);
                    $results[] = $result;
                } catch (\Exception $e) {
                    $results[] = [
                        'success' => false, 
                        'tempPath' => $tempPath, 
                        'message' => $e->getMessage()
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
    protected function moveFile(string $tempPath, string $targetDirectory, string $disk, $tempDisk): array
    {
        if (!str_starts_with($tempPath, 'temp/')) {
            return ['success' => false, 'tempPath' => $tempPath, 'message' => 'Invalid temp path'];
        }

        if (!$tempDisk->exists($tempPath)) {
            return ['success' => false, 'tempPath' => $tempPath, 'message' => 'Temp file not found'];
        }

        $metadata = $this->getTempMeta($tempPath);
        $originalName = $metadata['original_name'] ?? basename($tempPath);
        
        $finalFileName = $this->generateFileName($originalName);
        $finalPath = trim($targetDirectory, '/') . '/' . $finalFileName;

        // Ensure target directory exists
        $targetDisk = Storage::disk($disk);
        $targetDir = dirname($finalPath);
        if (!$targetDisk->exists($targetDir)) {
            $targetDisk->makeDirectory($targetDir);
        }

        // Copy file using streaming
        $moved = $this->copyStream($tempDisk, $tempPath, $targetDisk, $finalPath);

        if ($moved) {
            $this->deleteTemp($tempPath);
            return [
                'success' => true,
                'tempPath' => $tempPath,
                'finalPath' => $finalPath,
                'url' => $targetDisk->url($finalPath),
                'metadata' => $metadata
            ];
        }

        return ['success' => false, 'tempPath' => $tempPath, 'message' => 'Failed to move file'];
    }

    /**
     * Get the temporary storage disk instance
     */
    public function getTempDisk()
    {
        return Storage::disk(config('filex.temp_disk', 'local'));
    }
}
