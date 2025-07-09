<?php

namespace DevWizard\Filex\Support;

use DevWizard\Filex\Rules\FilexMimes;
use DevWizard\Filex\Rules\FilexMin;
use DevWizard\Filex\Rules\FilexMax;
use DevWizard\Filex\Rules\FilexDimensions;
use DevWizard\Filex\Rules\FilexImage;
use DevWizard\Filex\Rules\FilexFile;
use DevWizard\Filex\Rules\FilexSize;
use DevWizard\Filex\Rules\FilexMimetypes;

/**
 * Helper class for creating Filex validation rules with Laravel-style syntax
 * 
 * Usage examples:
 * FilexRule::mimes('pdf,jpeg,png')
 * FilexRule::min(100)
 * FilexRule::max(10000)  
 * FilexRule::dimensions('min_width=100,min_height=200')
 * FilexRule::image()
 * FilexRule::file()
 * FilexRule::size(1024)
 * FilexRule::mimetypes('application/pdf,image/jpeg')
 */
class FilexRule
{
    /**
     * Create a MIME type validation rule
     */
    public static function mimes(string $mimes): FilexMimes
    {
        return new FilexMimes($mimes);
    }

    /**
     * Create a minimum size validation rule
     */
    public static function min(int $minSize): FilexMin
    {
        return new FilexMin($minSize);
    }

    /**
     * Create a maximum size validation rule
     */
    public static function max(int $maxSize): FilexMax
    {
        return new FilexMax($maxSize);
    }

    /**
     * Create an image dimensions validation rule
     */
    public static function dimensions(string $dimensions): FilexDimensions
    {
        return new FilexDimensions($dimensions);
    }

    /**
     * Create an image validation rule
     */
    public static function image(): FilexImage
    {
        return new FilexImage();
    }

    /**
     * Create a file validation rule
     */
    public static function file(): FilexFile
    {
        return new FilexFile();
    }

    /**
     * Create an exact size validation rule
     */
    public static function size(int $size): FilexSize
    {
        return new FilexSize($size);
    }

    /**
     * Create a MIME types validation rule
     */
    public static function mimetypes(string $mimeTypes): FilexMimetypes
    {
        return new FilexMimetypes($mimeTypes);
    }

    /**
     * Create multiple rules at once
     */
    public static function rules(array $rules): array
    {
        $validationRules = [];

        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'mimes:')) {
                $validationRules[] = self::mimes(substr($rule, 6));
            } elseif (str_starts_with($rule, 'min:')) {
                $validationRules[] = self::min((int) substr($rule, 4));
            } elseif (str_starts_with($rule, 'max:')) {
                $validationRules[] = self::max((int) substr($rule, 4));
            } elseif (str_starts_with($rule, 'dimensions:')) {
                $validationRules[] = self::dimensions(substr($rule, 11));
            } elseif ($rule === 'image') {
                $validationRules[] = self::image();
            } elseif ($rule === 'file') {
                $validationRules[] = self::file();
            } elseif (str_starts_with($rule, 'size:')) {
                $validationRules[] = self::size((int) substr($rule, 5));
            } elseif (str_starts_with($rule, 'mimetypes:')) {
                $validationRules[] = self::mimetypes(substr($rule, 10));
            }
        }

        return $validationRules;
    }

    /**
     * Parse a Laravel-style rule string
     */
    public static function parse(string $ruleString): array
    {
        $rules = explode('|', $ruleString);
        return self::rules($rules);
    }
}
