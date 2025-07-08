<?php

namespace DevWizard\Filex\Traits;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait HasFilex
{
    protected $filexService;

    /**
     * Get the filex service instance
     */
    protected function getFilexService(): FilexService
    {
        if (!$this->filexService) {
            $this->filexService = app(FilexService::class);
        }
        
        return $this->filexService;
    }

    /**
     * Process uploaded files from temp to permanent storage
     * 
     * @param Request $request
     * @param string $fieldName The form field name for file uploads
     * @param string $targetDirectory Target directory for permanent storage
     * @param string|null $disk Storage disk to use (defaults to config)
     * @param bool $required Whether files are required
     * @return array Array of successfully processed file paths
     */
    protected function processFiles(
        Request $request, 
        string $fieldName, 
        string $targetDirectory,
        ?string $disk = null,
        bool $required = false
    ): array {
        // Apply performance settings early for large file operations
        $memoryLimit = config('filex.performance.memory_limit', '1G');
        $timeLimit = config('filex.performance.time_limit', 600);
        
        ini_set('memory_limit', $memoryLimit);
        set_time_limit($timeLimit);
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        $tempPaths = $request->input($fieldName, []);
        
        // Handle single file upload (non-array input)
        if (!is_array($tempPaths)) {
            $tempPaths = $tempPaths ? [$tempPaths] : [];
        }

        // Validate required files
        if ($required && empty($tempPaths)) {
            throw new \InvalidArgumentException('At least one file is required for ' . $fieldName);
        }

        // If no files provided, return empty array
        if (empty($tempPaths)) {
            return [];
        }

        // Move files from temp to permanent storage using optimized batch processing
        $results = $this->getFilexService()->moveBatch(
            $tempPaths, 
            $targetDirectory, 
            $disk
        );

        // Extract successful file paths
        $successfulFiles = [];
        $failedFiles = [];

        foreach ($results as $result) {
            if ($result['success']) {
                $successfulFiles[] = $result['finalPath'];
            } else {
                $failedFiles[] = [
                    'tempPath' => $result['tempPath'],
                    'error' => $result['message']
                ];
            }
        }

        // Log any failed transfers
        if (!empty($failedFiles)) {
            Log::warning('Some files failed to move from temp storage', [
                'field' => $fieldName,
                'failed_files' => $failedFiles,
                'request_id' => $request->header('X-Request-ID', uniqid())
            ]);
        }

        // If some files failed and files are required, throw exception
        if ($required && empty($successfulFiles)) {
            throw new \RuntimeException('All file uploads failed to process');
        }

        return $successfulFiles;
    }

    /**
     * Process a single uploaded file
     * 
     * @param Request $request
     * @param string $fieldName
     * @param string $targetDirectory
     * @param string|null $disk
     * @param bool $required
     * @return string|null The final file path or null if no file
     */
    protected function processSingleFile(
        Request $request, 
        string $fieldName, 
        string $targetDirectory,
        ?string $disk = null,
        bool $required = false
    ): ?string {
        $files = $this->processFiles($request, $fieldName, $targetDirectory, $disk, $required);
        
        return empty($files) ? null : $files[0];
    }

    /**
     * Validate temporary file paths before processing
     * 
     * @param array $tempPaths
     * @return array Validation results
     */
    protected function validateFiles(array $tempPaths): array
    {
        $validFiles = [];
        $invalidFiles = [];

        foreach ($tempPaths as $tempPath) {
            $metadata = $this->getFilexService()->getTempMeta($tempPath);
            
            if ($metadata) {
                $validFiles[] = $tempPath;
            } else {
                $invalidFiles[] = $tempPath;
            }
        }

        return [
            'valid' => $validFiles,
            'invalid' => $invalidFiles,
            'total' => count($tempPaths),
            'valid_count' => count($validFiles),
            'invalid_count' => count($invalidFiles)
        ];
    }

    /**
     * Get information about uploaded files
     * 
     * @param array $tempPaths
     * @return array File information
     */
    protected function getFilesInfo(array $tempPaths): array
    {
        $filesInfo = [];

        foreach ($tempPaths as $tempPath) {
            $metadata = $this->getFilexService()->getTempMeta($tempPath);
            
            if ($metadata) {
                $filesInfo[] = [
                    'temp_path' => $tempPath,
                    'original_name' => $metadata['original_name'] ?? 'unknown',
                    'uploaded_at' => $metadata['uploaded_at'] ?? null,
                    'size' => $this->getFilexService()->getTempDisk()->size($tempPath),
                    'metadata' => $metadata
                ];
            }
        }

        return $filesInfo;
    }

    /**
     * Clean up temporary files (useful in case of validation errors)
     * 
     * @param array $tempPaths
     * @return array Cleanup results
     */
    protected function cleanupFiles(array $tempPaths): array
    {
        $cleaned = [];
        $failed = [];

        foreach ($tempPaths as $tempPath) {
            if ($this->getFilexService()->deleteTemp($tempPath)) {
                $cleaned[] = $tempPath;
            } else {
                $failed[] = $tempPath;
            }
        }

        return [
            'cleaned' => $cleaned,
            'failed' => $failed,
            'cleaned_count' => count($cleaned),
            'failed_count' => count($failed)
        ];
    }

    /**
     * Handle file upload validation rules for form requests
     * 
     * @param string $fieldName
     * @param bool $required
     * @param array $additionalRules
     * @return array Validation rules
     */
    protected function getValidationRules(
        string $fieldName, 
        bool $required = false, 
        array $additionalRules = []
    ): array {
        $rules = [
            $fieldName => array_merge(
                $required ? ['required'] : ['nullable'],
                ['array'],
                $additionalRules
            ),
            $fieldName . '.*' => [
                'string',
                'starts_with:temp/',
                function ($attribute, $value, $fail) {
                    // Validate that temp file exists and belongs to current session/user
                    $metadata = $this->getFilexService()->getTempMeta($value);
                    
                    if (!$metadata) {
                        $fail('The selected file is invalid or has expired.');
                        return;
                    }

                    $currentUserId = Auth::check() ? Auth::id() : null;
                    $currentSessionId = session()->getId();
                    
                    if ($metadata['user_id'] !== $currentUserId && $metadata['session_id'] !== $currentSessionId) {
                        $fail('The selected file does not belong to your session.');
                    }
                }
            ]
        ];

        return $rules;
    }

    /**
     * Prepare file upload data for database storage
     * 
     * @param array $filePaths Final file paths after processing
     * @param array $metadata Additional metadata to store
     * @return array Prepared data
     */
    protected function prepareFileData(array $filePaths, array $metadata = []): array
    {
        return [
            'files' => $filePaths,
            'file_count' => count($filePaths),
            'uploaded_at' => now(),
            'metadata' => $metadata
        ];
    }

    /**
     * Handle bulk file operations (useful for updating existing records)
     * 
     * @param Request $request
     * @param string $fieldName
     * @param array $existingFiles Current files to replace
     * @param string $targetDirectory
     * @param string|null $disk
     * @return array New file paths
     */
    protected function handleBulkUpdate(
        Request $request,
        string $fieldName,
        array $existingFiles,
        string $targetDirectory,
        ?string $disk = null
    ): array {
        // Process new uploaded files
        $newFiles = $this->processFiles($request, $fieldName, $targetDirectory, $disk, false);
        
        // Clean up old files if new files were uploaded
        if (!empty($newFiles) && !empty($existingFiles)) {
            $disk = $disk ?? config('filex.default_disk', 'public');
            
            foreach ($existingFiles as $oldFile) {
                try {
                    \Illuminate\Support\Facades\Storage::disk($disk)->delete($oldFile);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old file during bulk update', [
                        'file' => $oldFile,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        // Return new files if any were uploaded, otherwise keep existing files
        return !empty($newFiles) ? $newFiles : $existingFiles;
    }
    
    /**
     * Check if file extension is allowed
     * 
     * @param string $filename
     * @return bool
     */
    protected function isAllowedExtension(string $filename): bool
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return $this->getFilexService()->allowsExtension($extension);
    }
    
    /**
     * Check if MIME type is allowed
     * 
     * @param string $mimeType
     * @return bool
     */
    protected function isAllowedMimeType(string $mimeType): bool
    {
        return $this->getFilexService()->allowsMimeType($mimeType);
    }
    
    /**
     * Format file size in human readable format
     * 
     * @param int $bytes
     * @return string
     */
    protected function formatFileSize(int $bytes): string
    {
        return $this->getFilexService()->formatSize($bytes);
    }
    
    /**
     * Get file icon based on extension
     * 
     * @param string $filename
     * @return string
     */
    protected function getFileIcon(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return $this->getFilexService()->getFileIcon($extension);
    }
    
    /**
     * Generate unique filename
     * 
     * @param string $originalName
     * @return string
     */
    protected function generateFileName(string $originalName): string
    {
        return $this->getFilexService()->generateFileName($originalName);
    }
    
    /**
     * Validate temp file
     * 
     * @param string $tempPath
     * @param string $originalName
     * @return array
     */
    protected function validateTempFile(string $tempPath, string $originalName): array
    {
        return $this->getFilexService()->validateTemp($tempPath, $originalName);
    }
    
    /**
     * Get file validation errors for display
     * 
     * @param array $tempPaths
     * @return array
     */
    protected function getValidationErrors(array $tempPaths): array
    {
        $errors = [];
        
        foreach ($tempPaths as $tempPath) {
            $metadata = $this->getFilexService()->getTempMeta($tempPath);
            
            if ($metadata) {
                $originalName = $metadata['original_name'] ?? 'unknown';
                $validation = $this->validateTempFile($tempPath, $originalName);
                
                if (!$validation['valid']) {
                    $errors[] = [
                        'temp_path' => $tempPath,
                        'original_name' => $originalName,
                        'errors' => $validation['errors'] ?? []
                    ];
                }
            } else {
                $errors[] = [
                    'temp_path' => $tempPath,
                    'original_name' => 'unknown',
                    'errors' => ['File not found or expired']
                ];
            }
        }
        
        return $errors;
    }
    
    /**
     * Get storage disk instance
     * 
     * @param string|null $disk
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected function getDisk(?string $disk = null): \Illuminate\Contracts\Filesystem\Filesystem
    {
        $disk = $disk ?? config('filex.default_disk', 'public');
        return \Illuminate\Support\Facades\Storage::disk($disk);
    }
    
    /**
     * Get temp storage disk instance
     * 
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected function getTempDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return $this->getFilexService()->getTempDisk();
    }
    
    /**
     * Clean expired temp files
     * 
     * @return array
     */
    protected function cleanupExpired(): array
    {
        return $this->getFilexService()->cleanup();
    }
    
    /**
     * Move files with progress tracking
     * 
     * @param array $tempPaths
     * @param string $targetDirectory
     * @param string|null $disk
     * @param callable|null $progressCallback
     * @return array
     */
    protected function moveFilesWithProgress(
        array $tempPaths, 
        string $targetDirectory, 
        ?string $disk = null,
        ?callable $progressCallback = null
    ): array {
        $results = [];
        $total = count($tempPaths);
        
        foreach ($tempPaths as $index => $tempPath) {
            try {
                $result = $this->getFilexService()->moveFiles([$tempPath], $targetDirectory, $disk);
                $results[] = $result[0] ?? ['success' => false, 'tempPath' => $tempPath];
                
                if ($progressCallback) {
                    $progressCallback($index + 1, $total, $tempPath);
                }
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'tempPath' => $tempPath,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Get upload statistics
     * 
     * @param array $tempPaths
     * @return array
     */
    protected function getUploadStats(array $tempPaths): array
    {
        $stats = [
            'total_files' => count($tempPaths),
            'total_size' => 0,
            'valid_files' => 0,
            'invalid_files' => 0,
            'file_types' => [],
            'largest_file' => 0,
            'smallest_file' => PHP_INT_MAX
        ];
        
        foreach ($tempPaths as $tempPath) {
            $metadata = $this->getFilexService()->getTempMeta($tempPath);
            
            if ($metadata) {
                $stats['valid_files']++;
                
                // Get file size
                try {
                    $size = $this->getTempDisk()->size($tempPath);
                    $stats['total_size'] += $size;
                    $stats['largest_file'] = max($stats['largest_file'], $size);
                    $stats['smallest_file'] = min($stats['smallest_file'], $size);
                } catch (\Exception $e) {
                    // Ignore size calculation errors
                }
                
                // Track file types
                $originalName = $metadata['original_name'] ?? '';
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if ($extension) {
                    $stats['file_types'][$extension] = ($stats['file_types'][$extension] ?? 0) + 1;
                }
            } else {
                $stats['invalid_files']++;
            }
        }
        
        // Format sizes
        $stats['total_size_formatted'] = $this->formatFileSize($stats['total_size']);
        $stats['largest_file_formatted'] = $this->formatFileSize($stats['largest_file']);
        $stats['smallest_file_formatted'] = $stats['smallest_file'] === PHP_INT_MAX ? 'N/A' : $this->formatFileSize($stats['smallest_file']);
        
        return $stats;
    }
}
