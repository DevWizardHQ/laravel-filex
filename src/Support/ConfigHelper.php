<?php

namespace DevWizard\Filex\Support;

/**
 * Configuration helper for Filex package
 */
class ConfigHelper
{
    /**
     * Get Filex configuration value with caching
     */
    public static function get(string $key, $default = null)
    {
        return config("filex.{$key}", $default);
    }

    /**
     * Get default disk configuration
     */
    public static function getDefaultDisk(): string
    {
        return self::get('default_disk', config('filesystems.default', 'public'));
    }

    /**
     * Get temporary disk configuration
     */
    public static function getTempDisk(): string
    {
        return self::get('temp_disk', 'local');
    }

    /**
     * Get maximum file size in bytes
     */
    public static function getMaxFileSize(): int
    {
        return self::get('max_file_size', 10) * 1024 * 1024;
    }

    /**
     * Get temporary file expiry in hours
     */
    public static function getTempExpiryHours(): int
    {
        return self::get('temp_expiry_hours', 24);
    }

    /**
     * Get allowed file extensions
     */
    public static function getAllowedExtensions(): array
    {
        return self::get('allowed_extensions', []);
    }

    /**
     * Get allowed MIME types
     */
    public static function getAllowedMimeTypes(): array
    {
        return self::get('allowed_mime_types', []);
    }

    /**
     * Check if cleanup is enabled
     */
    public static function isCleanupEnabled(): bool
    {
        return self::get('cleanup.enabled', true);
    }

    /**
     * Get cleanup schedule
     */
    public static function getCleanupSchedule(): string
    {
        return self::get('cleanup.schedule', 'daily');
    }
}
