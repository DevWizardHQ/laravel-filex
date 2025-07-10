<?php

namespace DevWizard\Filex\Rules;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Contracts\Validation\ValidationRule;
use Closure;

/**
 * Filex image dimensions validation rule
 *
 * Usage: 'filex:dimensions:min_width=100,min_height=200,max_width=1000,max_height=1000,width=300,height=300'
 */
class FilexDimensions implements ValidationRule
{
    protected $constraints;
    protected $filexService;

    public function __construct(string $dimensions)
    {
        $this->constraints = $this->parseDimensions($dimensions);
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

        $filePath = $tempDisk->path($value);

        // Check if it's an image
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false) {
            $fail(__('filex::validation.must_be_image'));
            return;
        }

        [$width, $height] = $imageInfo;

        // Validate dimensions
        foreach ($this->constraints as $constraint => $value) {
            switch ($constraint) {
                case 'min_width':
                    if ($width < $value) {
                        $fail(__('filex::validation.min_width', ['value' => $value]));
                        return;
                    }
                    break;
                case 'max_width':
                    if ($width > $value) {
                        $fail(__('filex::validation.max_width', ['value' => $value]));
                        return;
                    }
                    break;
                case 'min_height':
                    if ($height < $value) {
                        $fail(__('filex::validation.min_height', ['value' => $value]));
                        return;
                    }
                    break;
                case 'max_height':
                    if ($height > $value) {
                        $fail(__('filex::validation.max_height', ['value' => $value]));
                        return;
                    }
                    break;
                case 'width':
                    if ($width != $value) {
                        $fail(__('filex::validation.exact_width', ['value' => $value]));
                        return;
                    }
                    break;
                case 'height':
                    if ($height != $value) {
                        $fail(__('filex::validation.exact_height', ['value' => $value]));
                        return;
                    }
                    break;
                case 'ratio':
                    $actualRatio = $width / $height;
                    if (abs($actualRatio - $value) > 0.01) {
                        $fail(__('filex::validation.aspect_ratio', ['value' => $value]));
                        return;
                    }
                    break;
            }
        }
    }

    protected function parseDimensions(string $dimensions): array
    {
        $constraints = [];
        $parts = explode(',', $dimensions);

        foreach ($parts as $part) {
            $part = trim($part);
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $constraints[trim($key)] = (float) trim($value);
            }
        }

        return $constraints;
    }
}
