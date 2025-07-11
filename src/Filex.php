<?php

namespace DevWizard\Filex;

use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Support\FilexResult;

/**
 * Laravel Filex - Modern File Upload Component
 *
 * Main class that provides convenient access to the FilexService
 * and serves as the primary entry point for the package.
 */
class Filex
{
    protected FilexService $service;

    public function __construct(FilexService $service)
    {
        $this->service = $service;
    }

    /**
     * Get the underlying service instance
     */
    public function service(): FilexService
    {
        return $this->service;
    }

    /**
     * Generate a unique filename for uploads
     */
    public function generateFileName(string $originalName): string
    {
        return $this->service->generateFileName($originalName);
    }

    /**
     * Validate a temporary file
     */
    public function validateTemp(string $tempPath, string $originalName): array
    {
        return $this->service->validateTemp($tempPath, $originalName);
    }

    /**
     * Move temporary files to permanent storage
     */
    public function moveFiles(array $tempPaths, string $targetDirectory, ?string $disk = null, ?string $visibility = null): FilexResult
    {
        $results = $this->service->moveFiles($tempPaths, $targetDirectory, $disk, $visibility);

        return new FilexResult($results);
    }

    /**
     * Move a single temporary file to permanent storage
     */
    public function moveFile(string $tempPath, string $targetDirectory, ?string $disk = null, ?string $visibility = null): FilexResult
    {
        $results = $this->service->moveFiles([$tempPath], $targetDirectory, $disk, $visibility);

        return new FilexResult($results);
    }

    /**
     * Clean up expired temporary files
     */
    public function cleanup(): array
    {
        return $this->service->cleanup();
    }

    /**
     * Move files with public visibility
     */
    public function moveFilesPublic(array $tempPaths, string $targetDirectory, ?string $disk = null): FilexResult
    {
        return $this->moveFiles($tempPaths, $targetDirectory, $disk, 'public');
    }

    /**
     * Move files with private visibility
     */
    public function moveFilesPrivate(array $tempPaths, string $targetDirectory, ?string $disk = null): FilexResult
    {
        return $this->moveFiles($tempPaths, $targetDirectory, $disk, 'private');
    }

    /**
     * Move single file with public visibility
     */
    public function moveFilePublic(string $tempPath, string $targetDirectory, ?string $disk = null): FilexResult
    {
        return $this->moveFile($tempPath, $targetDirectory, $disk, 'public');
    }

    /**
     * Move single file with private visibility
     */
    public function moveFilePrivate(string $tempPath, string $targetDirectory, ?string $disk = null): FilexResult
    {
        return $this->moveFile($tempPath, $targetDirectory, $disk, 'private');
    }
}
