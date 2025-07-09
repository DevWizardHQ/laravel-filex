<?php

namespace DevWizard\Filex\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

class FilexService
{
    /**
     * Static cache for configuration values
     */
    private static array $configCache = [];
    
    /**
     * Static cache for allowed extensions
     */
    private static ?array $allowedExtensions = null;
    
    /**
     * Static cache for allowed MIME types
     */
    private static ?array $allowedMimeTypes = null;
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
        
        return $slugName . '_' . $timestamp . '_' . $random . '.' . $extension;
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
     * Cached configuration getter
     */
    private function getCachedConfig(string $key, $default = null)
    {
        if (!isset(self::$configCache[$key])) {
            self::$configCache[$key] = config($key, $default);
        }
        
        return self::$configCache[$key];
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
                    
                    $diskInstance = Storage::disk($disk);
                    $results[] = [
                        'success' => true,
                        'tempPath' => $tempPath,
                        'finalPath' => $finalPath,
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
     * Validate file extension against allowed types with caching
     */
    public function allowsExtension(string $extension): bool
    {
        if (self::$allowedExtensions === null) {
            self::$allowedExtensions = array_flip(
                $this->getCachedConfig('filex.allowed_extensions', [])
            );
        }
        
        return isset(self::$allowedExtensions[strtolower($extension)]);
    }

    /**
     * Validate MIME type against allowed types with caching
     */
    public function allowsMimeType(string $mimeType): bool
    {
        if (self::$allowedMimeTypes === null) {
            self::$allowedMimeTypes = array_flip(
                $this->getCachedConfig('filex.allowed_mime_types', [])
            );
        }
        
        return isset(self::$allowedMimeTypes[$mimeType]);
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
                'metadata' => $metadata
            ];
        }

        return ['success' => false, 'tempPath' => $tempPath, 'message' => 'Failed to move file'];
    }

    /**
     * Enhanced file validation with multiple security layers
     */
    public function validateSecure(string $tempPath, string $originalName): array
    {
        try {
            $tempDisk = $this->getTempDisk();
            
            if (!$tempDisk->exists($tempPath)) {
                return ['valid' => false, 'message' => 'File not found'];
            }

            $filePath = $tempDisk->path($tempPath);
            $fileSize = $tempDisk->size($tempPath);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            // 1. Basic validations
            $basicValidation = $this->validateTemp($tempPath, $originalName);
            if (!$basicValidation['valid']) {
                return $basicValidation;
            }

            // 2. File signature validation
            if (!$this->validateFileSignature($filePath, $extension)) {
                return ['valid' => false, 'message' => 'File signature validation failed'];
            }

            // 3. Content-based validation for specific file types
            if (!$this->validateFileContent($filePath, $extension)) {
                return ['valid' => false, 'message' => 'File content validation failed'];
            }

            // 4. Security scanning
            if (!$this->scanForThreats($filePath, $originalName)) {
                return ['valid' => false, 'message' => 'Security scan failed'];
            }

            return ['valid' => true, 'message' => 'File passed all security validations'];

        } catch (\Exception $e) {
            Log::error('Enhanced file validation error: ' . $e->getMessage(), [
                'temp_path' => $tempPath,
                'original_name' => $originalName
            ]);
            return ['valid' => false, 'message' => 'Validation failed due to an error'];
        }
    }

    /**
     * Validate file signature (magic bytes)
     */
    protected function validateFileSignature(string $filePath, string $extension): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
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
            'gif' => ["GIF87a", "GIF89a"],
            'pdf' => ["%PDF-"],
            'zip' => ["PK\x03\x04"],
            'docx' => ["PK\x03\x04"],
            'xlsx' => ["PK\x03\x04"],
            'pptx' => ["PK\x03\x04"],
        ];

        if (!isset($signatures[$extension])) {
            return true; // No signature check for this extension
        }

        foreach ($signatures[$extension] as $signature) {
            if (str_starts_with($header, $signature)) {
                return true;
            }
        }

        return false;
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
                'error' => $e->getMessage()
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
        if (!$handle) {
            return false;
        }

        // Check PDF header
        $header = fread($handle, 8);
        if (!str_starts_with($header, '%PDF-')) {
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
        if (!class_exists('ZipArchive')) {
            return true; // Skip validation if ZipArchive not available
        }

        $zip = new \ZipArchive();
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
                'original_name' => $originalName
            ]);
            return false;
        }

        // 2. Check for suspicious file names
        if ($this->hasSuspiciousFileName($originalName)) {
            Log::alert('Suspicious filename detected', [
                'original_name' => $originalName
            ]);
            return false;
        }

        // 3. Scan file content for suspicious patterns
        if ($this->containsSuspiciousContent($filePath)) {
            Log::alert('Suspicious content detected', [
                'file_path' => basename($filePath),
                'original_name' => $originalName
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
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        $executableSignatures = [
            "\x4D\x5A", // PE/EXE files
            "\x7FELF", // ELF files
            "\xCF\xFA\xED\xFE", // Mach-O files
            "#!/bin/", // Shell scripts
            "#!/usr/bin/", // Shell scripts
        ];

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

        // Double extensions
        if (preg_match('/\.[a-z]{2,4}\.[a-z]{2,4}$/i', $filename)) {
            return true;
        }

        // Suspicious patterns
        $suspiciousPatterns = [
            '/\.(php|phtml|php3|php4|php5)$/i',
            '/\.(asp|aspx|jsp|cfm)$/i',
            '/\.(exe|bat|cmd|scr)$/i',
            '/\.(htaccess|htpasswd)$/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan file content for suspicious patterns
     */
    protected function containsSuspiciousContent(string $filePath): bool
    {
        // Only scan text-based files to avoid false positives
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $textExtensions = ['txt', 'html', 'htm', 'css', 'js', 'json', 'xml', 'csv'];
        
        if (!in_array($extension, $textExtensions)) {
            return false; // Skip binary files
        }

        $content = file_get_contents($filePath, false, null, 0, 10240); // Read first 10KB
        if ($content === false) {
            return false;
        }

        $suspiciousPatterns = [
            '/<\?php/i',
            '/<%[^>]*%>/i', // ASP tags
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
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
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
            $tempDisk = $this->getTempDisk();
            $quarantineDir = 'quarantine/' . date('Y/m/d');
            $quarantineFile = $quarantineDir . '/' . basename($tempPath) . '_' . time();
            
            // Create quarantine directory
            if (!$tempDisk->exists($quarantineDir)) {
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
                
                $tempDisk->put($quarantineFile . '.meta', json_encode($metadata));
                
                Log::alert('File quarantined', [
                    'original_path' => $tempPath,
                    'quarantine_path' => $quarantineFile,
                    'reason' => $reason
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to quarantine file: ' . $e->getMessage(), [
                'temp_path' => $tempPath,
                'reason' => $reason
            ]);
            return false;
        }
    }

    /**
     * Get the temporary storage disk instance
     */
    public function getTempDisk()
    {
        return Storage::disk(config('filex.temp_disk', 'local'));
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
            asset('vendor/filex/css/filex.css')
        ];

        $jsAssets = [
            asset('vendor/filex/js/dropzone.min.js'),
            asset('vendor/filex/js/filex.js')
        ];

        $output = '';

        // Add CSS assets
        foreach ($cssAssets as $css) {
            $output .= '<link rel="stylesheet" href="' . $css . '" />' . "\n";
        }

        // Add JS assets
        foreach ($jsAssets as $js) {
            $output .= '<script src="' . $js . '"></script>' . "\n";
        }

        // Add routes configuration
        $uploadRoute = route('filex.upload.temp');
        $deleteRoute = route('filex.temp.delete', ['filename' => '__FILENAME__']);
        
        $output .= '<script>' . "\n";
        $output .= 'window.filexRoutes = {' . "\n";
        $output .= '    upload: "' . $uploadRoute . '",' . "\n";
        $output .= '    delete: "' . $deleteRoute . '"' . "\n";
        $output .= '};' . "\n";
        $output .= '</script>' . "\n";

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
            asset('vendor/filex/css/filex.css')
        ];

        $jsAssets = [
            asset('vendor/filex/js/dropzone.min.js'),
            asset('vendor/filex/js/filex.js')
        ];

        $output = '';

        // Add CSS assets
        foreach ($cssAssets as $css) {
            $output .= '<link rel="stylesheet" href="' . $css . '" />' . "\n";
        }

        // Add JS assets
        foreach ($jsAssets as $js) {
            $output .= '<script src="' . $js . '"></script>' . "\n";
        }

        // Add routes configuration
        $uploadRoute = route('filex.upload.temp');
        $deleteRoute = route('filex.temp.delete', ['filename' => '__FILENAME__']);
        
        $output .= '<script>' . "\n";
        $output .= 'window.filexRoutes = {' . "\n";
        $output .= '    upload: "' . $uploadRoute . '",' . "\n";
        $output .= '    delete: "' . $deleteRoute . '"' . "\n";
        $output .= '};' . "\n";
        $output .= '</script>' . "\n";

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
            asset('vendor/filex/css/filex.css')
        ];

        $jsAssets = [
            asset('vendor/filex/js/dropzone.min.js'),
            asset('vendor/filex/js/filex.js')
        ];

        $output = '';

        // Add CSS assets
        foreach ($cssAssets as $css) {
            $output .= '<link rel="stylesheet" href="' . $css . '" />' . "\n";
        }

        // Add JS assets
        foreach ($jsAssets as $js) {
            $output .= '<script src="' . $js . '"></script>' . "\n";
        }

        return $output;
    }

    /**
     * Render Filex routes configuration
     *
     * @param string $uploadRoute
     * @param string $deleteRoute
     * @return string
     */
    public function renderFilexRoutes($uploadRoute, $deleteRoute)
    {
        $output = '<script>' . "\n";
        $output .= 'window.filexRoutes = {' . "\n";
        $output .= '    upload: "' . $uploadRoute . '",' . "\n";
        $output .= '    delete: "' . $deleteRoute . '"' . "\n";
        $output .= '};' . "\n";
        $output .= '</script>' . "\n";

        return $output;
    }

    /**
     * Optimized bulk file move with batching and performance monitoring
     */
    public function moveFilesBulk(array $tempPaths, string $targetDirectory, ?string $disk = null): array
    {
        PerformanceMonitor::startTimer('bulk_file_move');
        PerformanceMonitor::checkMemoryUsage('Before bulk file move');
        
        $disk = $disk ?? $this->getCachedConfig('filex.default_disk', 'public');
        $batchSize = $this->getCachedConfig('filex.performance.batch_size', 5);
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
        if (!$targetDisk->exists($targetDir)) {
            $targetDisk->makeDirectory($targetDir);
        }

        // Process files in batches for better performance
        $batches = array_chunk($validPaths, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            PerformanceMonitor::startTimer("batch_process_{$batchIndex}");
            $batchResults = $this->processMoveFileBatch($batch, $targetDirectory, $tempDisk, $targetDisk);
            $results = array_merge($results, $batchResults);
            PerformanceMonitor::endTimer("batch_process_{$batchIndex}", [
                'batch_size' => count($batch),
                'successful' => count(array_filter($batchResults, fn($r) => $r['success']))
            ]);
        }

        PerformanceMonitor::checkMemoryUsage('After bulk file move');
        PerformanceMonitor::endTimer('bulk_file_move', [
            'total_files' => count($tempPaths),
            'valid_files' => count($validPaths),
            'batches' => count($batches),
            'successful' => count(array_filter($results, fn($r) => $r['success']))
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
     * Process a batch of file moves
     */
    private function processMoveFileBatch(array $batch, string $targetDirectory, $tempDisk, $targetDisk): array
    {
        $results = [];
        
        foreach ($batch as $tempPath) {
            try {
                $metadata = $this->getTempMeta($tempPath);
                $originalName = $metadata['original_name'] ?? basename($tempPath);
                
                // Generate final filename
                $finalFileName = $this->generateFileName($originalName);
                $finalPath = trim($targetDirectory, '/') . '/' . $finalFileName;

                // Use existing copyStream method for consistency
                $moved = $this->copyStream($tempDisk, $tempPath, $targetDisk, $finalPath);

                if ($moved) {
                    // Clean up temp file
                    $this->deleteTemp($tempPath);
                    
                    $results[] = [
                        'success' => true,
                        'tempPath' => $tempPath,
                        'finalPath' => $finalPath,
                        'metadata' => $metadata
                    ];
                } else {
                    $results[] = ['success' => false, 'tempPath' => $tempPath, 'message' => 'Failed to move file'];
                }

            } catch (\Exception $e) {
                Log::error('Failed to move temp file in batch', [
                    'temp_path' => $tempPath,
                    'error' => $e->getMessage()
                ]);
                $results[] = ['success' => false, 'tempPath' => $tempPath, 'message' => $e->getMessage()];
            }
        }
        
        return $results;
    }
}
