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
            $fail('The :attribute must be a valid Filex temp file.');
            return;
        }

        $tempDisk = $this->filexService->getTempDisk();
        if (!$tempDisk->exists($value)) {
            $fail('The :attribute file not found.');
            return;
        }

        $filePath = $tempDisk->path($value);
        
        // Check if it's an image
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false) {
            $fail('The :attribute must be an image.');
            return;
        }

        [$width, $height] = $imageInfo;

        // Validate dimensions
        foreach ($this->constraints as $constraint => $value) {
            switch ($constraint) {
                case 'min_width':
                    if ($width < $value) {
                        $fail("The :attribute has invalid image dimensions. Minimum width is {$value}px.");
                        return;
                    }
                    break;
                case 'max_width':
                    if ($width > $value) {
                        $fail("The :attribute has invalid image dimensions. Maximum width is {$value}px.");
                        return;
                    }
                    break;
                case 'min_height':
                    if ($height < $value) {
                        $fail("The :attribute has invalid image dimensions. Minimum height is {$value}px.");
                        return;
                    }
                    break;
                case 'max_height':
                    if ($height > $value) {
                        $fail("The :attribute has invalid image dimensions. Maximum height is {$value}px.");
                        return;
                    }
                    break;
                case 'width':
                    if ($width != $value) {
                        $fail("The :attribute has invalid image dimensions. Width must be exactly {$value}px.");
                        return;
                    }
                    break;
                case 'height':
                    if ($height != $value) {
                        $fail("The :attribute has invalid image dimensions. Height must be exactly {$value}px.");
                        return;
                    }
                    break;
                case 'ratio':
                    $actualRatio = $width / $height;
                    if (abs($actualRatio - $value) > 0.01) {
                        $fail("The :attribute has invalid image dimensions. Aspect ratio must be {$value}.");
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
