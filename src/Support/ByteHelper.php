<?php

namespace DevWizard\Filex\Support;

/**
 * Utility class for byte conversion and formatting
 */
class ByteHelper
{
    /**
     * Convert PHP ini size value to bytes
     */
    public static function convertToBytes(string $size): int
    {
        $size = trim($size);

        if (empty($size)) {
            return 0;
        }

        $last = strtolower($size[strlen($size) - 1]);
        $number = (int) $size;

        switch ($last) {
            case 'g':
                $number *= 1024;
                // Fall through
            case 'm':
                $number *= 1024;
                // Fall through
            case 'k':
                $number *= 1024;
        }

        return $number;
    }

    /**
     * Format bytes to human-readable format
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
