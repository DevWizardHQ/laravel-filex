<?php

namespace DevWizard\Filex\Rules;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Closure;

/**
 * Comprehensive file validation rule for Laravel Filex
 *
 * This rule provides multi-layered validation to prevent security vulnerabilities:
 * 1. Extension validation (can be spoofed)
 * 2. MIME type validation (can be spoofed)
 * 3. File signature/magic bytes validation (most secure)
 * 4. Content analysis for additional security
 */
class ValidFileUpload implements ValidationRule
{
    protected $allowedExtensions;
    protected $allowedMimeTypes;
    protected $maxFileSize;
    protected $strict;
    protected $filexService;

    /**
     * Static cache for validation results to improve performance
     */
    private static array $validationCache = [];

    /**
     * Static cache for file signatures
     */
    private static array $signatureCache = [];

    public function __construct(
        ?array $allowedExtensions = null,
        ?array $allowedMimeTypes = null,
        ?int $maxFileSize = null,
        bool $strict = true
    ) {
        $this->allowedExtensions = $allowedExtensions ?? config('filex.validation.allowed_extensions', []);
        $this->allowedMimeTypes = $allowedMimeTypes ?? config('filex.validation.allowed_mime_types', []);
        $this->maxFileSize = $maxFileSize ?? (config('filex.storage.max_file_size', 10) * 1024 * 1024);
        $this->strict = $strict;
        $this->filexService = app(FilexService::class);
    }

    /**
     * Run the validation rule with caching optimization.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check if value is a temp file path
        if (!is_string($value) || !str_starts_with($value, 'temp/')) {
            $fail(__('filex::validation.invalid_upload'));
            return;
        }

        // Create cache key for this validation
        $cacheKey = md5($value . serialize($this->allowedExtensions) . serialize($this->allowedMimeTypes) . $this->maxFileSize);

        // Check cache first for performance
        if (isset(self::$validationCache[$cacheKey])) {
            $cached = self::$validationCache[$cacheKey];
            if (!$cached['valid']) {
                $fail($cached['message']);
            }
            return;
        }

        // Get file metadata
        $metadata = $this->filexService->getTempMeta($value);
        if (!$metadata) {
            $error = __('filex::validation.file_not_found_or_expired');
            self::$validationCache[$cacheKey] = ['valid' => false, 'message' => $error];
            $fail($error);
            return;
        }

        $originalName = $metadata['original_name'] ?? 'unknown';
        $tempDisk = $this->filexService->getTempDisk();

        if (!$tempDisk->exists($value)) {
            $error = __('filex::validation.file_not_found');
            self::$validationCache[$cacheKey] = ['valid' => false, 'message' => $error];
            $fail($error);
            return;
        }

        $filePath = $tempDisk->path($value);
        $fileSize = $tempDisk->size($value);

        // 1. File size validation
        if ($fileSize > $this->maxFileSize) {
            $fail(__('filex::validation.file_too_large', ['max' => $this->formatFileSize($this->maxFileSize)]));
            return;
        }

        // 2. Extension validation (basic layer)
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            $fail(__('filex::validation.invalid_mime_type', ['values' => implode(', ', $this->allowedExtensions)]));
            return;
        }

        // 3. MIME type validation (can be spoofed)
        $declaredMimeType = $metadata['mime_type'] ?? null;
        if ($declaredMimeType && !in_array($declaredMimeType, $this->allowedMimeTypes)) {
            $fail(__('filex::validation.invalid_mimetypes', ['values' => implode(', ', $this->allowedMimeTypes)]));
            return;
        }

        // 4. Real MIME type detection using finfo (more secure)
        $realMimeType = $this->detectRealMimeType($filePath);
        if (!in_array($realMimeType, $this->allowedMimeTypes)) {
            $fail(__('filex::translations.errors.file_content_mismatch'));
            return;
        }

        // 5. File signature validation (most secure)
        if ($this->strict) {
            if (!$this->validateFileSignature($filePath, $extension)) {
                $fail(__('filex::translations.errors.file_signature_validation_failed'));
                return;
            }
        }

        // 6. Additional security checks for specific file types
        if (!$this->performAdditionalSecurityChecks($filePath, $extension, $realMimeType)) {
            $error = 'File failed security validation.';
            self::$validationCache[$cacheKey] = ['valid' => false, 'message' => $error];
            $fail($error);
            return;
        }

        // Cache successful validation result
        self::$validationCache[$cacheKey] = ['valid' => true, 'message' => null];

        // Limit cache size to prevent memory issues
        if (count(self::$validationCache) > 100) {
            self::$validationCache = array_slice(self::$validationCache, -50, null, true);
        }
    }

    /**
     * Detect real MIME type using finfo
     */
    protected function detectRealMimeType(string $filePath): string
    {
        static $finfo = null;

        if (!file_exists($filePath)) {
            return 'application/octet-stream';
        }

        // Reuse finfo resource to improve performance
        if ($finfo === null) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
        }

        $mimeType = finfo_file($finfo, $filePath);
        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * Validate file signature (magic bytes) for security with caching
     */
    protected function validateFileSignature(string $filePath, string $extension): bool
    {
        static $signatures = null;

        if (!is_readable($filePath)) {
            return false;
        }

        // Check cache first
        $fileHash = md5_file($filePath);
        if (isset(self::$signatureCache[$fileHash])) {
            return self::$signatureCache[$fileHash];
        }

        // Initialize signatures array once
        if ($signatures === null) {
            $signatures = [
                'jpg' => ["\xFF\xD8\xFF", "\xFF\xD8\xFF\xE0", "\xFF\xD8\xFF\xE1"],
                'jpeg' => ["\xFF\xD8\xFF", "\xFF\xD8\xFF\xE0", "\xFF\xD8\xFF\xE1"],
                'png' => ["\x89PNG\r\n\x1A\n"],
                'gif' => ["GIF87a", "GIF89a"],
                'webp' => ["RIFF", "WEBP"],
                'bmp' => ["BM"],
                'ico' => ["\x00\x00\x01\x00"],
                'pdf' => ["%PDF-"],
                'docx' => ["PK\x03\x04"],
                'xlsx' => ["PK\x03\x04"],
                'pptx' => ["PK\x03\x04"],
                'doc' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"],
                'xls' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"],
                'rtf' => ["{\\rtf"],
                'zip' => ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"],
                'rar' => ["Rar!\x1A\x07\x00", "Rar!\x1A\x07\x01\x00"],
                '7z' => ["7z\xBC\xAF\x27\x1C"],
                'tar' => ["ustar\x00\x30\x30", "ustar\x20\x20\x00"],
                'gz' => ["\x1F\x8B"],
                'mp3' => ["ID3", "\xFF\xFB", "\xFF\xF3", "\xFF\xF2"],
                'wav' => ["RIFF", "WAVE"],
                'flac' => ["fLaC"],
                'ogg' => ["OggS"],
                'mp4' => ["\x00\x00\x00\x18ftypmp4", "\x00\x00\x00\x20ftypmp4"],
                'avi' => ["RIFF", "AVI "],
                'mov' => ["\x00\x00\x00\x14ftypqt"],
                'mkv' => ["\x1A\x45\xDF\xA3"],
            ];
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 32);
        fclose($handle);

        if ($header === false) {
            self::$signatureCache[$fileHash] = false;
            return false;
        }

        $result = $this->checkFileSignature($header, $extension);

        // Cache the result
        self::$signatureCache[$fileHash] = $result;

        // Limit cache size
        if (count(self::$signatureCache) > 50) {
            self::$signatureCache = array_slice(self::$signatureCache, -25, null, true);
        }

        return $result;
    }

    /**
     * Check file signature against known patterns
     */
    protected function checkFileSignature(string $header, string $extension): bool
    {
        static $signatures = null;

        if ($signatures === null) {
            $signatures = [
                'jpg' => ["\xFF\xD8\xFF", "\xFF\xD8\xFF\xE0", "\xFF\xD8\xFF\xE1"],
                'jpeg' => ["\xFF\xD8\xFF", "\xFF\xD8\xFF\xE0", "\xFF\xD8\xFF\xE1"],
                'png' => ["\x89PNG\r\n\x1A\n"],
                'gif' => ["GIF87a", "GIF89a"],
                'webp' => ["RIFF", "WEBP"],
                'bmp' => ["BM"],
                'ico' => ["\x00\x00\x01\x00"],
                'pdf' => ["%PDF-"],
                'docx' => ["PK\x03\x04"],
                'xlsx' => ["PK\x03\x04"],
                'pptx' => ["PK\x03\x04"],
                'doc' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"],
                'xls' => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"],
                'rtf' => ["{\\rtf"],
                'zip' => ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"],
                'rar' => ["Rar!\x1A\x07\x00", "Rar!\x1A\x07\x01\x00"],
                '7z' => ["7z\xBC\xAF\x27\x1C"],
                'tar' => ["ustar\x00\x30\x30", "ustar\x20\x20\x00"],
                'gz' => ["\x1F\x8B"],
                'mp3' => ["ID3", "\xFF\xFB", "\xFF\xF3", "\xFF\xF2"],
                'wav' => ["RIFF", "WAVE"],
                'flac' => ["fLaC"],
                'ogg' => ["OggS"],
                'mp4' => ["\x00\x00\x00\x18ftypmp4", "\x00\x00\x00\x20ftypmp4"],
                'avi' => ["RIFF", "AVI "],
                'mov' => ["\x00\x00\x00\x14ftypqt"],
                'mkv' => ["\x1A\x45\xDF\xA3"],
            ];
        }

        if (!isset($signatures[$extension])) {
            return true; // No signature check for this extension
        }

        foreach ($signatures[$extension] as $signature) {
            if (str_starts_with($header, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Perform additional security checks for specific file types
     */
    protected function performAdditionalSecurityChecks(string $filePath, string $extension, string $mimeType): bool
    {
        try {
            // Check for executable files disguised as other types
            if ($this->isExecutableFile($filePath)) {
                Log::warning('Attempted upload of executable file disguised as: ' . $extension, [
                    'file_path' => basename($filePath),
                    'extension' => $extension,
                    'mime_type' => $mimeType
                ]);
                return false;
            }

            // Specific checks for image files
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                return $this->validateImageFile($filePath);
            }

            // Specific checks for PDF files
            if ($extension === 'pdf') {
                return $this->validatePdfFile($filePath);
            }

            // Specific checks for document files
            if (in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
                return $this->validateOfficeDocument($filePath);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error during additional security checks: ' . $e->getMessage(), [
                'file_path' => basename($filePath),
                'extension' => $extension
            ]);
            return false;
        }
    }

    /**
     * Check if file is an executable
     */
    protected function isExecutableFile(string $filePath): bool
    {
        $executableSignatures = [
            "\x4D\x5A", // PE/EXE files (Windows)
            "\x7FELF", // ELF files (Linux)
            "\xCF\xFA\xED\xFE", // Mach-O files (macOS)
            "\xFE\xED\xFA\xCE", // Mach-O files (macOS, big endian)
            "\xCA\xFE\xBA\xBE", // Universal binary (macOS)
            "#!/bin/", // Shell scripts
            "#!/usr/bin/", // Shell scripts
        ];

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        foreach ($executableSignatures as $signature) {
            if (str_starts_with($header, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate image files using getimagesize
     */
    protected function validateImageFile(string $filePath): bool
    {
        try {
            $imageInfo = @getimagesize($filePath);
            return $imageInfo !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate PDF files
     */
    protected function validatePdfFile(string $filePath): bool
    {
        try {
            $handle = fopen($filePath, 'rb');
            if (!$handle) {
                return false;
            }

            // Check PDF header
            $header = fread($handle, 8);
            if (!str_starts_with($header, '%PDF-')) {
                fclose($handle);
                return false;
            }

            // Check for PDF trailer
            fseek($handle, -256, SEEK_END);
            $trailer = fread($handle, 256);
            fclose($handle);

            return str_contains($trailer, '%%EOF');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate Office documents
     */
    protected function validateOfficeDocument(string $filePath): bool
    {
        try {
            $handle = fopen($filePath, 'rb');
            if (!$handle) {
                return false;
            }

            $header = fread($handle, 8);
            fclose($handle);

            // Modern Office documents (OOXML) are ZIP files
            if (str_starts_with($header, "PK\x03\x04")) {
                return $this->validateZipBasedOfficeDocument($filePath);
            }

            // Legacy Office documents (OLE format)
            if (str_starts_with($header, "\xD0\xCF\x11\xE0")) {
                return true; // Basic OLE validation
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Validate ZIP-based Office documents (OOXML)
     */
    protected function validateZipBasedOfficeDocument(string $filePath): bool
    {
        try {
            if (!class_exists('ZipArchive')) {
                return true; // Can't validate without ZipArchive
            }

            $zip = new \ZipArchive();
            $result = $zip->open($filePath, \ZipArchive::RDONLY);

            if ($result !== true) {
                return false;
            }

            // Check for required Office document structure
            $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
            $hasRels = $zip->locateName('_rels/.rels') !== false;

            $zip->close();

            return $hasContentTypes && $hasRels;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Static factory method for common validation scenarios
     */
    public static function forImages(int $maxSizeMB = 5): self
    {
        return new self(
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'],
            $maxSizeMB * 1024 * 1024,
            true
        );
    }

    /**
     * Static factory method for documents
     */
    public static function forDocuments(int $maxSizeMB = 10): self
    {
        return new self(
            ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'],
            [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'application/rtf',
            ],
            $maxSizeMB * 1024 * 1024,
            true
        );
    }

    /**
     * Static factory method for specific file type
     */
    public static function forType(string $extension, string $mimeType, int $maxSizeMB = 10): self
    {
        return new self(
            [$extension],
            [$mimeType],
            $maxSizeMB * 1024 * 1024,
            true
        );
    }

    /**
     * Format file size in human readable format
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
