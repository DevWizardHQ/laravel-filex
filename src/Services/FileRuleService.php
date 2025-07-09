<?php

namespace DevWizard\Filex\Services;

use DevWizard\Filex\Rules\ValidFileUpload;

/**
 * File Rule Service for creating validation rules
 */
class FileRuleService
{
    /**
     * Create validation rule for images
     */
    public function forImages(int $maxSizeMB = 5): ValidFileUpload
    {
        return ValidFileUpload::forImages($maxSizeMB);
    }

    /**
     * Create validation rule for documents
     */
    public function forDocuments(int $maxSizeMB = 10): ValidFileUpload
    {
        return ValidFileUpload::forDocuments($maxSizeMB);
    }

    /**
     * Create validation rule for archives
     */
    public function forArchives(int $maxSizeMB = 50): ValidFileUpload
    {
        return new ValidFileUpload(
            ['zip', 'rar', '7z', 'tar', 'gz'],
            [
                'application/zip',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
                'application/x-tar',
                'application/gzip',
            ],
            $maxSizeMB * 1024 * 1024,
            true
        );
    }

    /**
     * Create validation rule for audio files
     */
    public function forAudio(int $maxSizeMB = 20): ValidFileUpload
    {
        return new ValidFileUpload(
            ['mp3', 'wav', 'flac', 'ogg'],
            [
                'audio/mpeg',
                'audio/wav',
                'audio/flac',
                'audio/ogg',
            ],
            $maxSizeMB * 1024 * 1024,
            true
        );
    }

    /**
     * Create validation rule for video files
     */
    public function forVideo(int $maxSizeMB = 100): ValidFileUpload
    {
        return new ValidFileUpload(
            ['mp4', 'avi', 'mov', 'mkv'],
            [
                'video/mp4',
                'video/avi',
                'video/quicktime',
                'video/x-matroska',
            ],
            $maxSizeMB * 1024 * 1024,
            true
        );
    }

    /**
     * Create validation rule for specific file type
     */
    public function forType(string $extension, string $mimeType, int $maxSizeMB = 10): ValidFileUpload
    {
        return ValidFileUpload::forType($extension, $mimeType, $maxSizeMB);
    }

    /**
     * Create custom validation rule
     */
    public function custom(
        array $allowedExtensions,
        array $allowedMimeTypes,
        int $maxSizeMB = 10,
        bool $strict = true
    ): ValidFileUpload {
        return new ValidFileUpload(
            $allowedExtensions,
            $allowedMimeTypes,
            $maxSizeMB * 1024 * 1024,
            $strict
        );
    }

    /**
     * Create lenient validation rule (less strict)
     */
    public function lenient(
        array $allowedExtensions,
        array $allowedMimeTypes,
        int $maxSizeMB = 10
    ): ValidFileUpload {
        return new ValidFileUpload(
            $allowedExtensions,
            $allowedMimeTypes,
            $maxSizeMB * 1024 * 1024,
            false // Not strict
        );
    }

    /**
     * Create validation rule for common web files
     */
    public function forWeb(int $maxSizeMB = 10): ValidFileUpload
    {
        return new ValidFileUpload(
            ['html', 'css', 'js', 'json', 'xml'],
            [
                'text/html',
                'text/css',
                'application/javascript',
                'text/javascript',
                'application/json',
                'application/xml',
                'text/xml',
            ],
            $maxSizeMB * 1024 * 1024,
            false // Less strict for text files
        );
    }
}
