<?php

namespace DevWizard\Filex\Traits;

use DevWizard\Filex\Facades\Filex;
use Illuminate\Http\Request;

/**
 * HasFilex Trait - Clean, simple file upload API
 *
 * Provides essential file upload functionality for models and controllers.
 * Method names match the main Filex class for consistency.
 * Focuses on common use cases with a clean, simple interface.
 */
trait HasFilex
{
    /**
     * Move single file from request to permanent storage
     *
     * @param  string  $fieldName  Form field name containing temp path
     * @param  string  $directory  Target directory (e.g., 'uploads/avatars')
     * @param  string|null  $disk  Storage disk (defaults to config)
     * @param  string|null  $visibility  File visibility ('public' or 'private')
     * @return string|null Final file path or null if no file
     */
    protected function moveFile(Request $request, string $fieldName, string $directory, ?string $disk = null, ?string $visibility = null): ?string
    {
        $tempPath = $request->input($fieldName);

        if (empty($tempPath)) {
            return null;
        }

        $result = Filex::moveFile($tempPath, $directory, $disk, $visibility);

        return $result->getPath();
    }

    /**
     * Move multiple files from request to permanent storage
     *
     * @param  string  $fieldName  Form field name containing array of temp paths
     * @param  string  $directory  Target directory
     * @param  string|null  $disk  Storage disk
     * @param  string|null  $visibility  File visibility ('public' or 'private')
     * @return array Array of final file paths
     */
    protected function moveFiles(Request $request, string $fieldName, string $directory, ?string $disk = null, ?string $visibility = null): array
    {
        $tempPaths = $request->input($fieldName, []);

        if (empty($tempPaths) || ! is_array($tempPaths)) {
            return [];
        }

        $result = Filex::moveFiles($tempPaths, $directory, $disk, $visibility);

        return $result->getPaths();
    }

    /**
     * Get validation rules for file upload fields
     *
     * @return array Laravel validation rules
     */
    protected function getFileValidationRules(string $fieldName, bool $required = false): array
    {
        return [
            $fieldName => $required ? ['required', 'string', 'starts_with:temp/'] : ['nullable', 'string', 'starts_with:temp/'],
        ];
    }

    /**
     * Get validation rules for multiple file upload fields
     *
     * @return array Laravel validation rules
     */
    protected function getFilesValidationRules(string $fieldName, bool $required = false): array
    {
        return [
            $fieldName => $required ? ['required', 'array'] : ['nullable', 'array'],
            $fieldName.'.*' => ['string', 'starts_with:temp/'],
        ];
    }

    /**
     * Clean up temporary files (useful if validation fails)
     *
     * @return int Number of files cleaned up
     */
    protected function cleanupTempFiles(array $tempPaths): int
    {
        $cleaned = 0;

        foreach ($tempPaths as $tempPath) {
            if (Filex::service()->deleteTemp($tempPath)) {
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Move file with public visibility
     *
     * @param  string  $fieldName  Form field name containing temp path
     * @param  string  $directory  Target directory
     * @param  string|null  $disk  Storage disk
     * @return string|null Final file path or null if no file
     */
    protected function moveFilePublic(Request $request, string $fieldName, string $directory, ?string $disk = null): ?string
    {
        return $this->moveFile($request, $fieldName, $directory, $disk, 'public');
    }

    /**
     * Move file with private visibility
     *
     * @param  string  $fieldName  Form field name containing temp path
     * @param  string  $directory  Target directory
     * @param  string|null  $disk  Storage disk
     * @return string|null Final file path or null if no file
     */
    protected function moveFilePrivate(Request $request, string $fieldName, string $directory, ?string $disk = null): ?string
    {
        return $this->moveFile($request, $fieldName, $directory, $disk, 'private');
    }

    /**
     * Move files with public visibility
     *
     * @param  string  $fieldName  Form field name containing array of temp paths
     * @param  string  $directory  Target directory
     * @param  string|null  $disk  Storage disk
     * @return array Array of final file paths
     */
    protected function moveFilesPublic(Request $request, string $fieldName, string $directory, ?string $disk = null): array
    {
        return $this->moveFiles($request, $fieldName, $directory, $disk, 'public');
    }

    /**
     * Move files with private visibility
     *
     * @param  string  $fieldName  Form field name containing array of temp paths
     * @param  string  $directory  Target directory
     * @param  string|null  $disk  Storage disk
     * @return array Array of final file paths
     */
    protected function moveFilesPrivate(Request $request, string $fieldName, string $directory, ?string $disk = null): array
    {
        return $this->moveFiles($request, $fieldName, $directory, $disk, 'private');
    }
}
