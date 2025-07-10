<?php

declare(strict_types=1);

namespace DevWizard\Filex\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * File upload rate limiting middleware
 *
 * Provides rate limiting for file upload endpoints:
 * - Configurable rate limiting per IP and user
 * - Customizable time windows and attempt limits
 * - Enable/disable functionality
 */
class FileUploadSecurityMiddleware
{
    /**
     * Static cache for security settings
     */
    private static array $securitySettings = [];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Basic security checks
            if (!$this->validateBasicSecurity($request)) {
                return response()->json([
                    'success' => false,
                    'message' => __('filex::translations.security_validation_failed'),
                    'error_type' => 'security_error'
                ], 403);
            }

            // Rate limiting with adaptive thresholds
            if (!$this->checkRateLimits($request)) {
                return response()->json([
                    'success' => false,
                    'message' => __('filex::translations.rate_limit_exceeded'),
                    'error_type' => 'rate_limit_error',
                    'retry_after' => $this->getRetryAfter()
                ], 429);
            }

            // Content type validation
            if (!$this->validateContentType($request)) {
                return response()->json([
                    'success' => false,
                    'message' => __('filex::translations.invalid_content_type'),
                    'error_type' => 'content_type_error'
                ], 415);
            }

            // Header validation
            if (!$this->validateHeaders($request)) {
                return response()->json([
                    'success' => false,
                    'message' => __('filex::translations.invalid_headers'),
                    'error_type' => 'header_error'
                ], 400);
            }

            // Check for suspicious patterns
            if ($this->detectSuspiciousPatterns($request)) {
                $this->logSuspiciousActivity($request);
                return response()->json([
                    'success' => false,
                    'message' => __('filex::translations.suspicious_activity_detected'),
                    'error_type' => 'security_error'
                ], 403);
            }

            return $next($request);
        } catch (\Exception $e) {
            Log::error('Security middleware error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => __('filex::translations.security_check_failed'),
                'error_type' => 'security_error'
            ], 500);
        }
    }

    /**
     * Validate basic security requirements
     */
    private function validateBasicSecurity(Request $request): bool
    {
        // Validate request method
        if (!in_array($request->method(), ['POST', 'DELETE'])) {
            return false;
        }

        // Validate request size
        $maxSize = config('filex.max_file_size', 10) * 1024 * 1024;
        if ($request->header('Content-Length') > $maxSize) {
            return false;
        }

        // Validate file path
        $filePath = $request->route('filename');
        if ($filePath && (
            str_contains($filePath, '..') ||
            str_contains($filePath, '/') ||
            !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filePath)
        )) {
            return false;
        }

        return true;
    }

    /**
     * Check rate limits with adaptive thresholds
     */
    private function checkRateLimits(Request $request): bool
    {
        $key = $this->getRateLimitKey($request);
        $maxAttempts = $this->getMaxAttempts();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        RateLimiter::hit($key, $this->getDecayMinutes() * 60);

        // Adjust rate limit based on system load
        $this->adjustRateLimits();

        return true;
    }

    /**
     * Get rate limit key based on IP and session
     */
    private function getRateLimitKey(Request $request): string
    {
        $ip = $request->ip();
        $session = $request->session()->getId();
        return 'filex_upload:' . md5($ip . $session);
    }

    /**
     * Get adaptive max attempts based on system load
     */
    private function getMaxAttempts(): int
    {
        $load = sys_getloadavg()[0];
        $baseLimit = config('filex.rate_limit.max_attempts', 60);

        if ($load > 2.0) {
            return (int)($baseLimit * 0.5); // 50% reduction
        } elseif ($load > 1.0) {
            return (int)($baseLimit * 0.75); // 25% reduction
        }

        return $baseLimit;
    }

    /**
     * Get decay minutes for rate limiting
     */
    private function getDecayMinutes(): int
    {
        return config('filex.rate_limit.decay_minutes', 1);
    }

    /**
     * Get retry after timestamp
     */
    private function getRetryAfter(): int
    {
        $key = $this->getRateLimitKey(request());
        return RateLimiter::availableIn($key);
    }

    /**
     * Adjust rate limits based on system metrics
     */
    private function adjustRateLimits(): void
    {
        $cacheKey = 'filex_rate_limit_adjustment';
        if (!Cache::has($cacheKey)) {
            $load = sys_getloadavg()[0];
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->getMemoryLimit();

            $adjustment = 1.0;

            // Adjust for CPU load
            if ($load > 2.0) {
                $adjustment *= 0.5;
            } elseif ($load > 1.0) {
                $adjustment *= 0.75;
            }

            // Adjust for memory usage
            $memoryPercentage = ($memoryUsage / $memoryLimit) * 100;
            if ($memoryPercentage > 85) {
                $adjustment *= 0.5;
            } elseif ($memoryPercentage > 75) {
                $adjustment *= 0.75;
            }

            Cache::put($cacheKey, $adjustment, now()->addMinutes(5));
        }
    }

    /**
     * Get memory limit in bytes
     */
    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int)$limit;
        $unit = strtolower(substr($limit, -1));

        switch ($unit) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Validate content type headers
     */
    private function validateContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        // Allow multipart/form-data for file uploads
        if ($request->isMethod('POST') && $request->hasFile('file')) {
            return str_contains($contentType, 'multipart/form-data');
        }

        // Allow application/json for other requests
        return str_contains($contentType, 'application/json');
    }

    /**
     * Validate required and forbidden headers
     */
    private function validateHeaders(Request $request): bool
    {
        // Required headers
        $requiredHeaders = ['Host', 'User-Agent'];
        foreach ($requiredHeaders as $header) {
            if (!$request->header($header)) {
                return false;
            }
        }

        // Forbidden headers that might indicate tampering
        $forbiddenHeaders = ['X-Forwarded-Host', 'X-Original-URL', 'X-Rewrite-URL'];
        foreach ($forbiddenHeaders as $header) {
            if ($request->header($header)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Detect suspicious patterns in the request
     */
    private function detectSuspiciousPatterns(Request $request): bool
    {
        // Check for suspicious query parameters
        $suspiciousParams = ['eval', 'exec', 'system', 'shell'];
        foreach ($suspiciousParams as $param) {
            if ($request->query($param) !== null) {
                return true;
            }
        }

        // Check for suspicious file extensions in upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            $suspiciousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps'];

            if (in_array($extension, $suspiciousExtensions)) {
                return true;
            }
        }

        // Check for suspicious patterns in headers
        $headers = $request->headers->all();
        $suspiciousPatterns = [
            'eval\(',
            'exec\(',
            'system\(',
            'shell_exec',
            '\$_GET',
            '\$_POST',
            '\$_REQUEST',
            '<script',
            'javascript:',
            'data:text/html'
        ];

        foreach ($headers as $header) {
            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', implode(' ', $header))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Log suspicious activity for monitoring
     */
    private function logSuspiciousActivity(Request $request): void
    {
        Log::warning('Suspicious activity detected', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'path' => $request->path(),
            'headers' => $request->headers->all(),
            'input' => $request->except(['file']),
            'timestamp' => now()->toIso8601String()
        ]);
    }
}
