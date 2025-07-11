<?php

namespace DevWizard\Filex\Rules;

use Closure;
use DevWizard\Filex\Services\FilexService;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Filex MIME types validation rule (exact MIME type matching)
 *
 * Usage: 'filex:mimetypes:application/pdf,image/jpeg'
 */
class FilexMimetypes implements ValidationRule
{
    protected $allowedMimeTypes;

    protected $filexService;

    public function __construct(string $mimeTypes)
    {
        $this->allowedMimeTypes = array_map('trim', explode(',', $mimeTypes));
        $this->filexService = app(FilexService::class);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_starts_with($value, 'temp/')) {
            $fail(__('filex::validation.temp_file'));

            return;
        }

        $tempDisk = $this->filexService->getTempDisk();
        if (! $tempDisk->exists($value)) {
            $fail(__('filex::validation.file_not_found'));

            return;
        }

        $filePath = $tempDisk->path($value);
        $realMimeType = $this->detectRealMimeType($filePath);

        if (! in_array($realMimeType, $this->allowedMimeTypes)) {
            $fail(__('filex::validation.invalid_mimetypes', ['values' => implode(', ', $this->allowedMimeTypes)]));
        }
    }

    protected function detectRealMimeType(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return 'application/octet-stream';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }
}
