<?php

declare(strict_types=1);

namespace DevWizard\Filex\Rules;

use Closure;
use DevWizard\Filex\Services\FilexService;
use Illuminate\Contracts\Validation\ValidationRule;

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
        if (! is_string($value) || ! str_starts_with($value, 'temp/')) {
            $fail(__('filex::validation.temp_file'));

            return;
        }

        $metadata = $this->filexService->getTempMeta($value);
        if (! $metadata) {
            $fail(__('filex::validation.file_not_found_or_expired'));

            return;
        }

        $tempDisk = $this->filexService->getTempDisk();
        if (! $tempDisk->exists($value)) {
            $fail(__('filex::validation.file_not_found'));

            return;
        }

        $filePath = $tempDisk->path($value);
        if (! is_readable($filePath)) {
            $fail(__('filex::validation.file_not_readable'));
        }
    }
}
