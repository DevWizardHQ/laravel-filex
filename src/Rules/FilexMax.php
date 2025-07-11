<?php

namespace DevWizard\Filex\Rules;

use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Support\ByteHelper;
use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

/**
 * Filex maximum file size validation rule
 *
 * Usage: 'filex:max:10000' (10000 bytes)
 */
class FilexMax implements ValidationRule
{
    protected $maxSize;
    protected $filexService;

    public function __construct(int $maxSize)
    {
        $this->maxSize = $maxSize;
        $this->filexService = app(FilexService::class);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !str_starts_with($value, 'temp/')) {
            $fail(__('filex::validation.temp_file'));
            return;
        }

        $tempDisk = $this->filexService->getTempDisk();
        if (!$tempDisk->exists($value)) {
            $fail(__('filex::validation.file_not_found'));
            return;
        }

        $fileSize = $tempDisk->size($value);

        if ($fileSize > $this->maxSize) {
            $fail(__('filex::validation.file_too_large', ['max' => ByteHelper::formatBytes($this->maxSize)]));
        }
    }
}
