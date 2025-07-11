<?php

namespace DevWizard\Filex\Rules;

use Closure;
use DevWizard\Filex\Services\FilexService;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Filex image validation rule
 *
 * Usage: 'filex:image'
 */
class FilexImage implements ValidationRule
{
    protected $filexService;

    public function __construct()
    {
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

        // Check if it's an image using getimagesize
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false) {
            $fail(__('filex::validation.must_be_image'));

            return;
        }

        // Additional check for valid image MIME types
        $allowedMimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/svg+xml',
        ];

        $realMimeType = $this->detectRealMimeType($filePath);
        if (! in_array($realMimeType, $allowedMimes)) {
            $fail(__('filex::validation.must_be_image'));
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
