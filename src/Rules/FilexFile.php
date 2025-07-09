<?php

namespace DevWizard\Filex\Rules;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

/**
 * Filex file validation rule (basic file existence and validity)
 * 
 * Usage: 'filex:file'
 */
class FilexFile implements ValidationRule
{
    protected $filexService;

    public function __construct()
    {
        $this->filexService = app(FilexService::class);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !str_starts_with($value, 'temp/')) {
            $fail('The :attribute must be a valid Filex temp file.');
            return;
        }

        $metadata = $this->filexService->getTempMeta($value);
        if (!$metadata) {
            $fail('The :attribute file not found or expired.');
            return;
        }

        $tempDisk = $this->filexService->getTempDisk();
        if (!$tempDisk->exists($value)) {
            $fail('The :attribute file not found.');
            return;
        }

        $filePath = $tempDisk->path($value);
        if (!is_readable($filePath)) {
            $fail('The :attribute file is not readable.');
        }
    }
}
