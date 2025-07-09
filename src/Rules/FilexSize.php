<?php

namespace DevWizard\Filex\Rules;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

/**
 * Filex file size validation rule
 * 
 * Usage: 'filex:size:1024' (exactly 1024 bytes)
 */
class FilexSize implements ValidationRule
{
    protected $expectedSize;
    protected $filexService;

    public function __construct(int $expectedSize)
    {
        $this->expectedSize = $expectedSize;
        $this->filexService = app(FilexService::class);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !str_starts_with($value, 'temp/')) {
            $fail('The :attribute must be a valid Filex temp file.');
            return;
        }

        $tempDisk = $this->filexService->getTempDisk();
        if (!$tempDisk->exists($value)) {
            $fail('The :attribute file not found.');
            return;
        }

        $fileSize = $tempDisk->size($value);

        if ($fileSize !== $this->expectedSize) {
            $fail('The :attribute must be exactly ' . $this->formatBytes($this->expectedSize) . '.');
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
