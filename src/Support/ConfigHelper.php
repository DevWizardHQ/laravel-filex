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
        return self::get('storage.disks.default', config('filesystems.default', 'public'));
    }

    /**
     * Get temporary disk configuration
     */
    public static function getTempDisk(): string
    {
        return self::get('storage.disks.temp', 'local');
    }

    /**
     * Get maximum file size in bytes
     */
    public static function getMaxFileSize(): int
    {
        return self::get('storage.max_file_size', 10) * 1024 * 1024;
    }

    /**
     * Get temporary file expiry in hours
     */
    public static function getTempExpiryHours(): int
    {
        return self::get('storage.temp_expiry_hours', 24);
    }

    /**
     * Get allowed file extensions
     */
    public static function getAllowedExtensions(): array
    {
        return self::get('validation.allowed_extensions', []);
    }

    /**
     * Get allowed MIME types
     */
    public static function getAllowedMimeTypes(): array
    {
        return self::get('validation.allowed_mime_types', []);
    }

    /**
     * Check if cleanup is enabled
     */
    public static function isCleanupEnabled(): bool
    {
        return self::get('system.cleanup.enabled', true);
    }

    /**
     * Get cleanup schedule
     */
    public static function getCleanupSchedule(): string
    {
        return self::get('system.cleanup.schedule', 'daily');
    }

    /**
     * Check if suspicious detection is enabled
     */
    public static function isSuspiciousDetectionEnabled(): bool
    {
        return self::get('security.suspicious_detection.enabled', true);
    }

    /**
     * Get suspicious query parameters
     */
    public static function getSuspiciousQueryParams(): array
    {
        return self::get('security.suspicious_query_params', []);
    }

    /**
     * Get suspicious file extensions
     */
    public static function getSuspiciousExtensions(): array
    {
        return self::get('security.suspicious_extensions', []);
    }

    /**
     * Get suspicious header patterns
     */
    public static function getSuspiciousHeaderPatterns(): array
    {
        return self::get('security.suspicious_header_patterns', []);
    }

    /**
     * Get rate limit max attempts
     */
    public static function getRateLimitMaxAttempts(): int
    {
        return self::get('performance.rate_limiting.ip_limit', 50);
    }

    /**
     * Get rate limit decay minutes
     */
    public static function getRateLimitDecayMinutes(): int
    {
        return self::get('performance.rate_limiting.time_window', 3600) / 60;
    }
}
