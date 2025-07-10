<?php

// config for DevWizard/Filex
return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The default disk where permanent files will be stored after being
    | moved from the temporary location.
    |
    */
    'default_disk' => env('FILEX_DISK', config('filesystems.default', 'public')),

    /*
    |--------------------------------------------------------------------------
    | Temporary Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk where temporary files will be stored during upload processing.
    | This should typically be 'local' for security and performance reasons.
    |
    */
    'temp_disk' => env('FILEX_TEMP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | The maximum file size allowed for uploads in megabytes.
    |
    */
    'max_file_size' => env('FILEX_MAX_SIZE', 10),

    /*
    |--------------------------------------------------------------------------
    | Temporary File Expiry
    |--------------------------------------------------------------------------
    |
    | How long temporary files should be kept before being cleaned up (in hours).
    |
    */
    'temp_expiry_hours' => env('FILEX_TEMP_EXPIRY', 24),

    /*
    |--------------------------------------------------------------------------
    | Allowed File Extensions
    |--------------------------------------------------------------------------
    |
    | List of file extensions that are allowed to be uploaded.
    |
    */
    'allowed_extensions' => [
        // Images
        'jpg',
        'jpeg',
        'png',
        'gif',
        'bmp',
        'webp',
        'svg',
        'tiff',
        'tif',
        'ico',

        // Documents
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'txt',
        'rtf',
        'csv',
        'odt',
        'ods',
        'odp',

        // Archives
        'zip',
        'rar',
        '7z',
        'tar',
        'gz',
        'bz2',

        // Audio
        'mp3',
        'wav',
        'ogg',
        'aac',
        'flac',
        'm4a',

        // Video
        'mp4',
        'avi',
        'mov',
        'wmv',
        'flv',
        'webm',
        'mkv',
        '3gp',

        // Text/Code files
        'html',
        'htm',
        'xml',
        'json',
        'css',
        'js',
        'php',
        'py',
        'java',
        'cpp',
        'c',
        'h',
        'sql',
        'md',
        'log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME Types
    |--------------------------------------------------------------------------
    |
    | List of MIME types that are allowed to be uploaded. This provides
    | an additional layer of security beyond file extensions.
    |
    */
    'allowed_mime_types' => [
        // Images
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/webp',
        'image/svg+xml',
        'image/tiff',
        'image/x-icon',
        'image/vnd.microsoft.icon',

        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'text/plain',
        'text/rtf',
        'text/csv',
        'application/rtf',

        // Archives
        'application/zip',
        'application/x-rar-compressed',
        'application/x-rar',
        'application/rar',
        'application/x-7z-compressed',
        'application/x-tar',
        'application/gzip',
        'application/x-gzip',
        'application/x-compressed',
        'application/x-zip-compressed',

        // Audio
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/wave',
        'audio/x-wav',
        'audio/ogg',
        'audio/aac',
        'audio/x-aac',
        'audio/flac',
        'audio/x-flac',
        'audio/m4a',
        'audio/mp4',

        // Video
        'video/mp4',
        'video/x-msvideo',
        'video/avi',
        'video/quicktime',
        'video/x-ms-wmv',
        'video/x-flv',
        'video/webm',
        'video/x-matroska',
        'video/mkv',
        'video/3gpp',
        'video/x-ms-asf',

        // Text files
        'text/html',
        'text/xml',
        'application/xml',
        'application/json',
        'text/json',
        'text/css',
        'text/javascript',
        'application/javascript',

        // Other common types
        'application/octet-stream', // Generic binary
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunk Upload Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for chunked file uploads.
    |
    */
    'chunk' => [
        'size' => env('FILEX_CHUNK_SIZE', 1048576), // 1MB
        'max_retries' => env('FILEX_CHUNK_RETRIES', 3),
        'timeout' => env('FILEX_CHUNK_TIMEOUT', 30000), // 30 seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the route settings for the file upload endpoints.
    |
    */
    'routes' => [
        'prefix' => env('FILEX_ROUTE_PREFIX', 'filex'),
        'domain' => env('FILEX_ROUTE_DOMAIN', null),
        'middleware' => env('FILEX_ROUTE_MIDDLEWARE', []),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Schedule
    |--------------------------------------------------------------------------
    |
    | Configuration for the automatic cleanup command.
    |
    */
    'cleanup' => [
        'enabled' => env('FILEX_CLEANUP_ENABLED', true),
        'schedule' => env('FILEX_CLEANUP_SCHEDULE', 'daily'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to optimize performance for large file uploads.
    |
    */
    'performance' => [
        'memory_limit' => env('FILEX_MEMORY_LIMIT', '1G'),
        'time_limit' => env('FILEX_TIME_LIMIT', 600), // 10 minutes
        'parallel_uploads' => env('FILEX_PARALLEL', 2),
        'chunk_threshold' => env('FILEX_CHUNK_THRESHOLD', 50 * 1024 * 1024), // 50MB
        'defer_validation' => env('FILEX_DEFER_VALIDATION', true),
        'batch_size' => env('FILEX_BATCH_SIZE', 5), // Number of files to process in batch
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Performance Optimizations
    |--------------------------------------------------------------------------
    |
    | Additional performance settings for large-scale applications.
    |
    */
    'optimization' => [
        'enable_caching' => env('FILEX_ENABLE_CACHING', true),
        'cache_ttl' => env('FILEX_CACHE_TTL', 3600), // 1 hour
        'enable_compression' => env('FILEX_ENABLE_COMPRESSION', true),
        'compression_level' => env('FILEX_COMPRESSION_LEVEL', 6),
        'max_concurrent_uploads' => env('FILEX_MAX_CONCURRENT', 10),
        'database_optimization' => env('FILEX_DB_OPTIMIZATION', true),
        'lazy_loading' => env('FILEX_LAZY_LOADING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for upload rate limiting to prevent abuse.
    |
    */
    'rate_limiting' => [
        'enabled' => env('FILEX_RATE_LIMITING_ENABLED', true),
        'ip_limit' => env('FILEX_RATE_IP_LIMIT', 50), // uploads per time window per IP
        'user_limit' => env('FILEX_RATE_USER_LIMIT', 100), // uploads per time window per user
        'time_window' => env('FILEX_RATE_TIME_WINDOW', 3600), // time window in seconds (1 hour)
        'suspend_time' => env('FILEX_RATE_SUSPEND_TIME', 3600), // suspend duration in seconds
        'message' => env('FILEX_RATE_LIMIT_MESSAGE', 'Too many upload attempts. Please try again later.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Debugging
    |--------------------------------------------------------------------------
    |
    | Settings for monitoring performance and debugging issues.
    |
    */
    'monitoring' => [
        'enable_metrics' => env('FILEX_ENABLE_METRICS', false),
        'log_performance' => env('FILEX_LOG_PERFORMANCE', false),
        'max_log_entries' => env('FILEX_MAX_LOG_ENTRIES', 1000),
        'enable_profiling' => env('FILEX_ENABLE_PROFILING', false),
    ],
];
