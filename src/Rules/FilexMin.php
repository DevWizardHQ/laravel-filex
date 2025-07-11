<?php

namespace DevWizard\Filex\Rules;

use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Support\ByteHelper;
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
            $fail(__('filex::validation.temp_file'));
            return;
        }

        $tempDisk = $this->filexService->getTempDisk();
        if (!$tempDisk->exists($value)) {
            $fail(__('filex::validation.file_not_found'));
            return;
        }

        $fileSize = $tempDisk->size($value);

        if ($fileSize < $this->minSize) {
            $fail(__('filex::validation.file_too_small', ['min' => ByteHelper::formatBytes($this->minSize)]));
        }
    }
}
