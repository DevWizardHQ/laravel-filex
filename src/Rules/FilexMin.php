<?php

namespace DevWizard\Filex\Rules;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

/**
 * Filex minimum file size validation rule
 * 
 * Usage: 'filex:min:100' (100 bytes)
 */
class FilexMin implements ValidationRule
{
    protected $minSize;
    protected $filexService;

    public function __construct(int $minSize)
    {
        $this->minSize = $minSize;
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

        if ($fileSize < $this->minSize) {
            $fail('The :attribute must be at least ' . $this->formatBytes($this->minSize) . '.');
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
