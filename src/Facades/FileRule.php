<?php

namespace DevWizard\Filex\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * FileRule Facade for easy validation rule access
 *
 * @method static \DevWizard\Filex\Rules\ValidFileUpload forImages(int $maxSizeMB = 5)
 * @method static \DevWizard\Filex\Rules\ValidFileUpload forDocuments(int $maxSizeMB = 10)
 * @method static \DevWizard\Filex\Rules\ValidFileUpload forArchives(int $maxSizeMB = 50)
 * @method static \DevWizard\Filex\Rules\ValidFileUpload forAudio(int $maxSizeMB = 20)
 * @method static \DevWizard\Filex\Rules\ValidFileUpload forVideo(int $maxSizeMB = 100)
 * @method static \DevWizard\Filex\Rules\ValidFileUpload forType(string $extension, string $mimeType, int $maxSizeMB = 10)
 * @method static \DevWizard\Filex\Rules\ValidFileUpload custom(array $allowedExtensions, array $allowedMimeTypes, int $maxSizeMB = 10, bool $strict = true)
 * @method static \DevWizard\Filex\Rules\ValidFileUpload lenient(array $allowedExtensions, array $allowedMimeTypes, int $maxSizeMB = 10)
 * @method static \DevWizard\Filex\Rules\ValidFileUpload forWeb(int $maxSizeMB = 10)
 */
class FileRule extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'filex.file-rule';
    }
}
