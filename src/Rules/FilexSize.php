<?php

namespace DevWizard\Filex\Rules;

use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Support\ByteHelper;
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
            $fail(__('filex::validation.temp_file'));
            return;
        }

        $tempDisk = $this->filexService->getTempDisk();
        if (!$tempDisk->exists($value)) {
            $fail(__('filex::validation.file_not_found'));
            return;
        }

        $fileSize = $tempDisk->size($value);

        if ($fileSize !== $this->expectedSize) {
            $fail(__('filex::validation.file_size_exact', ['size' => ByteHelper::formatBytes($this->expectedSize)]));
        }
    }
}
