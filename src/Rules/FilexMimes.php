<?php

namespace DevWizard\Filex\Rules;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Closure;

/**
 * Filex MIME type validation rule
 *
 * Usage: 'filex:mimes:pdf,jpeg,png'
 */
class FilexMimes implements ValidationRule
{
    protected $allowedMimes;
    protected $filexService;

    /**
     * Static cache for extension to MIME type mappings
     */
    private static ?array $extensionMimesCache = null;

    public function __construct(string $mimes)
    {
        // Convert extensions to MIME types using cached mapping
        $extensions = explode(',', $mimes);
        $this->allowedMimes = $this->extensionsToMimes($extensions);
        $this->filexService = app(FilexService::class);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !str_starts_with($value, 'temp/')) {
            $fail(__('filex::validation.temp_file'));
            return;
        }

        $metadata = $this->filexService->getTempMeta($value);
        if (!$metadata) {
            $fail(__('filex::validation.file_not_found_or_expired'));
            return;
        }

        $tempDisk = $this->filexService->getTempDisk();
        if (!$tempDisk->exists($value)) {
            $fail(__('filex::validation.file_not_found'));
            return;
        }

        $filePath = $tempDisk->path($value);
        $realMimeType = $this->detectRealMimeType($filePath);

        if (!in_array($realMimeType, $this->allowedMimes)) {
            $allowedExtensions = array_keys($this->mimesToExtensions());
            $fail(__('filex::validation.invalid_mime_type', ['values' => implode(', ', $allowedExtensions)]));
        }
    }

    protected function detectRealMimeType(string $filePath): string
    {
        static $finfo = null;

        if (!file_exists($filePath)) {
            return 'application/octet-stream';
        }

        // Reuse finfo resource
        if ($finfo === null) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
        }

        $mimeType = finfo_file($finfo, $filePath);
        return $mimeType ?: 'application/octet-stream';
    }

    protected function extensionsToMimes(array $extensions): array
    {
        $mimeMap = $this->mimesToExtensions();
        $mimes = [];

        foreach ($extensions as $ext) {
            $ext = strtolower(trim($ext));
            if (isset($mimeMap[$ext])) {
                $mimes = array_merge($mimes, (array) $mimeMap[$ext]);
            }
        }

        return array_unique($mimes);
    }

    protected function mimesToExtensions(): array
    {
        return [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'bmp' => ['image/bmp'],
            'svg' => ['image/svg+xml'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'txt' => ['text/plain'],
            'csv' => ['text/csv', 'application/csv'],
            'rtf' => ['application/rtf'],
            'zip' => ['application/zip'],
            'rar' => ['application/x-rar-compressed'],
            '7z' => ['application/x-7z-compressed'],
            'tar' => ['application/x-tar'],
            'gz' => ['application/gzip'],
            'mp3' => ['audio/mpeg'],
            'wav' => ['audio/wav'],
            'flac' => ['audio/flac'],
            'ogg' => ['audio/ogg'],
            'mp4' => ['video/mp4'],
            'avi' => ['video/avi', 'video/x-msvideo'],
            'mov' => ['video/quicktime'],
            'mkv' => ['video/x-matroska'],
            'html' => ['text/html'],
            'css' => ['text/css'],
            'js' => ['application/javascript', 'text/javascript'],
            'json' => ['application/json'],
            'xml' => ['application/xml', 'text/xml'],
        ];
    }
}
