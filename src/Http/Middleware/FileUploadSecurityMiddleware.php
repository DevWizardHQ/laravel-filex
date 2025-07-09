<?php

namespace DevWizard\Filex\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * File upload security middleware
 *
 * Provides additional security layers for file uploads:
 * - Rate limiting per IP and user
 * - File type validation at request level
 * - Suspicious activity detection
 * - Request size validation
 */
class FileUploadSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Rate limiting - prevent abuse
        // if (!$this->checkRateLimit($request)) {
        //     Log::warning('File upload rate limit exceeded', [
        //         'ip' => $request->ip(),
        //         'user_id' => Auth::id(),
        //         'user_agent' => $request->userAgent()
        //     ]);

        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Too many upload attempts. Please try again later.'
        //     ], 429);
        // }

        // 2. Validate request has file
        if (!$request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'No file provided'
            ], 422);
        }

        // 3. Basic file validation
        $file = $request->file('file');
        if (!$file->isValid()) {
            Log::warning('Invalid file upload attempt', [
                'ip' => $request->ip(),
                'user_id' => Auth::id(),
                'error' => $file->getErrorMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid file upload: ' . $file->getErrorMessage()
            ], 422);
        }

        // 4. Check for suspicious file names
        if ($this->hasSuspiciousFileName($file->getClientOriginalName())) {
            Log::alert('Suspicious file name detected', [
                'filename' => $file->getClientOriginalName(),
                'ip' => $request->ip(),
                'user_id' => Auth::id(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File name contains invalid characters'
            ], 422);
        }

        // 5. Validate file extension against blacklist
        if ($this->isBlacklistedExtension($file->getClientOriginalName())) {
            Log::alert('Blacklisted file extension upload attempt', [
                'filename' => $file->getClientOriginalName(),
                'ip' => $request->ip(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File type not allowed'
            ], 422);
        }

        // 6. Check file size against PHP limits
        $maxSize = $this->getMaxUploadSize();
        if ($file->getSize() > $maxSize) {
            return response()->json([
                'success' => false,
                'message' => 'File size exceeds maximum allowed size'
            ], 422);
        }

        // 7. Validate MIME type at request level
        if (!$this->isAllowedMimeType($file->getMimeType())) {
            Log::warning('Disallowed MIME type upload attempt', [
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'ip' => $request->ip(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File type not allowed'
            ], 422);
        }

        return $next($request);
    }

    /**
     * Check rate limiting for file uploads
     */
    protected function checkRateLimit(Request $request): bool
    {
        $key = 'file-upload:' . $request->ip();
        $userKey = Auth::check() ? 'file-upload-user:' . Auth::id() : null;

        // IP-based rate limiting: 50 uploads per hour
        if (RateLimiter::tooManyAttempts($key, 50)) {
            return false;
        }

        // User-based rate limiting: 100 uploads per hour
        if ($userKey && RateLimiter::tooManyAttempts($userKey, 100)) {
            return false;
        }

        // Hit the rate limiter
        RateLimiter::hit($key, 3600); // 1 hour
        if ($userKey) {
            RateLimiter::hit($userKey, 3600);
        }

        return true;
    }

    /**
     * Check for suspicious file names
     */
    protected function hasSuspiciousFileName(string $filename): bool
    {
        // Check for null bytes
        if (str_contains($filename, "\0")) {
            return true;
        }

        // Check for path traversal attempts
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return true;
        }

        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/\.(php|phtml|php3|php4|php5|phps|pht|phar)$/i',
            '/\.(js|vbs|bat|cmd|com|exe|scr)$/i',
            '/\.(asp|aspx|jsp|cfm|pl|py|rb)$/i',
            '/\.(htaccess|htpasswd)$/i',
            '/^(con|prn|aux|nul|com[1-9]|lpt[1-9])$/i', // Windows reserved names
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                return true;
            }
        }

        // Check for extremely long filenames
        if (strlen($filename) > 255) {
            return true;
        }

        // Check for files with no extension (suspicious)
        if (!str_contains($filename, '.')) {
            return true;
        }

        return false;
    }

    /**
     * Check if file extension is blacklisted
     */
    protected function isBlacklistedExtension(string $filename): bool
    {
        $blacklistedExtensions = [
            // Executable files
            'exe',
            'bat',
            'cmd',
            'com',
            'scr',
            'pif',
            'msi',
            'gadget',
            // Script files
            'php',
            'phtml',
            'php3',
            'php4',
            'php5',
            'phps',
            'pht',
            'phar',
            'asp',
            'aspx',
            'jsp',
            'cfm',
            'pl',
            'py',
            'rb',
            'sh',
            'bash',
            'js',
            'vbs',
            'ps1',
            'psm1',
            // Archive files with potential for code execution
            'jar',
            'war',
            'ear',
            // Database files
            'sql',
            'db',
            'sqlite',
            'sqlite3',
            // System files
            'sys',
            'dll',
            'drv',
            'ocx',
            // Configuration files
            'htaccess',
            'htpasswd',
            'ini',
            'conf',
            'config',
            // Other potentially dangerous
            'iso',
            'dmg',
            'pkg',
            'deb',
            'rpm',
        ];

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $blacklistedExtensions);
    }

    /**
     * Check if MIME type is allowed
     */
    protected function isAllowedMimeType(?string $mimeType): bool
    {
        if (!$mimeType) {
            return false;
        }

        $allowedMimeTypes = config('filex.allowed_mime_types', []);

        // If no configuration is set, deny all uploads for security
        if (empty($allowedMimeTypes)) {
            return false;
        }

        return in_array($mimeType, $allowedMimeTypes);
    }

    /**
     * Get maximum upload size from PHP configuration
     */
    protected function getMaxUploadSize(): int
    {
        $uploadMax = $this->parseSize(ini_get('upload_max_filesize'));
        $postMax = $this->parseSize(ini_get('post_max_size'));
        $configMax = config('filex.max_file_size', 10) * 1024 * 1024;

        return min($uploadMax, $postMax, $configMax);
    }

    /**
     * Parse size string to bytes
     */
    protected function parseSize(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;

        switch ($last) {
            case 'g':
                $size *= 1024;
                // no break
            case 'm':
                $size *= 1024;
                // no break
            case 'k':
                $size *= 1024;
        }

        return $size;
    }
}
