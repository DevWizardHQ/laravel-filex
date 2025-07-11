<?php

namespace DevWizard\Filex\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Basic file upload middleware
 *
 * Provides basic HTTP-level file upload checks:
 * - File presence validation
 * - Basic file upload errors
 * - Request structure validation
 */
class FileUploadMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Validate request has file
        if (! $request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'No file provided',
            ], 422);
        }

        // 2. Basic file upload error checking
        $file = $request->file('file');
        if (! $file->isValid()) {
            $error = $file->getError();
            $errorMessage = match ($error) {
                UPLOAD_ERR_INI_SIZE => 'File exceeds the maximum allowed size configured on the server',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum allowed size for this form',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded. Please try again',
                UPLOAD_ERR_NO_FILE => 'No file was provided for upload',
                UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Server configuration error: Cannot write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by a PHP extension',
                default => 'Unknown file upload error occurred'
            };

            Log::warning('Invalid file upload attempt', [
                'ip' => $request->ip(),
                'user_id' => Auth::id(),
                'error_code' => $error,
                'error_message' => $errorMessage,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $error,
            ], 422);
        }

        return $next($request);
    }
}
