<?php

namespace DevWizard\Filex\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if rate limiting is enabled
        if (!config('filex.rate_limiting.enabled', true)) {
            return $next($request);
        }

        // Rate limiting - prevent abuse with configurable limits
        if (!$this->checkRateLimit($request)) {
            Log::warning('File upload rate limit exceeded', [
                'ip' => $request->ip(),
                'user_id' => Auth::id(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => false,
                'message' => config('filex.rate_limiting.message', 'Too many upload attempts. Please try again later.')
            ], 429);
        }

        return $next($request);
    }

    /**
     * Check rate limiting for file uploads with configurable settings
     */
    protected function checkRateLimit(Request $request): bool
    {
        $ipKey = 'file-upload:' . $request->ip();
        $userKey = Auth::check() ? 'file-upload-user:' . Auth::id() : null;

        // Get configuration values
        $ipLimit = config('filex.rate_limiting.ip_limit', 50);
        $userLimit = config('filex.rate_limiting.user_limit', 100);
        $timeWindow = config('filex.rate_limiting.time_window', 3600); // 1 hour default
        $suspendTime = config('filex.rate_limiting.suspend_time', $timeWindow);

        // IP-based rate limiting
        if (RateLimiter::tooManyAttempts($ipKey, $ipLimit)) {
            return false;
        }

        // User-based rate limiting
        if ($userKey && RateLimiter::tooManyAttempts($userKey, $userLimit)) {
            return false;
        }

        // Hit the rate limiter with configured time window
        RateLimiter::hit($ipKey, $suspendTime);
        if ($userKey) {
            RateLimiter::hit($userKey, $suspendTime);
        }

        return true;
    }
}
