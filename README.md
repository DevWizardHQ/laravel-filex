# Laravel Filex

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devwizardhq/laravel-filex.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-filex)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/devwizardhq/laravel-filex/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/devwizardhq/laravel-filex/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/devwizardhq/laravel-filex/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/devwizardhq/laravel-filex/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/devwizardhq/laravel-filex.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-filex)

Laravel Filex is a powerful and reusable Blade component that brings modern, asynchronous file uploads to Laravel applications. It supports features like drag-and-drop uploads, real-time progress indicators, preview rendering, chunked upload for large files, and temporary file handling with finalization on form submission.

## Features

-   🎯 **Easy Integration**: Drop-in Blade component for instant file upload functionality
-   📁 **Drag & Drop**: Modern drag-and-drop interface with visual feedback
-   📊 **Progress Tracking**: Real-time upload progress with visual indicators
-   🔄 **Chunked Uploads**: Handle large files with automatic chunking
-   ⏱️ **Temporary Storage**: Safe temporary file handling with automatic cleanup
-   🔒 **Validation**: Client-side and server-side validation support
-   🛡️ **Security**: Advanced threat detection, file signature validation, and quarantine system
-   🎨 **Customizable**: Extensive configuration options and styling flexibility
-   ☁️ **Cloud Ready**: Support for local, S3, and other Laravel storage drivers
-   🌐 **Localization**: Multi-language support
-   🧹 **Auto Cleanup**: Scheduled cleanup of orphaned temporary files

## Installation

You can install the package via composer:

```bash
composer require devwizardhq/laravel-filex
```

### Auto-Publishing (Recommended)

**Laravel Filex now automatically publishes its assets and configuration when the package is installed!** The package will automatically:

-   Publish the configuration file to `config/filex.php`
-   Publish CSS and JavaScript assets to `public/vendor/filex/`
-   Only publish files that don't already exist (won't overwrite existing files)

If you want to manually run the installation or need to republish files:

```bash
php artisan filex:install
```

### Manual Installation

Alternatively, you can publish files manually:

```bash
# Publish configuration
php artisan vendor:publish --tag="filex-config"

# Publish assets
php artisan vendor:publish --tag="filex-assets"

# Publish views (optional)
php artisan vendor:publish --tag="filex-views"
```

### Quick Installation Command Options

The `php artisan filex:install` command supports several options:

```bash
# Force overwrite existing files
php artisan filex:install --force

# Only publish configuration
php artisan filex:install --only-config

# Only publish assets
php artisan filex:install --only-assets

# Run silently (no prompts or output)
php artisan filex:install --auto
```

### Asset Integration

Add the assets directive to your Blade layout:

```blade
@filexAssets
```

This directive automatically includes all required CSS, JavaScript, and route configurations.

## Usage

### Basic Usage

```blade
<form method="POST" action="/upload">
    @csrf

    <x-filex-uploader
        name="documents"
        :multiple="true"
        :required="true"
    />

    <button type="submit">Submit</button>
</form>
```

**Note:** Make sure to include `@filexAssets` in your layout to load required CSS, JavaScript, and routes.

### Advanced Usage

```blade
<x-filex-uploader
    name="files"
    :multiple="true"
    :maxFiles="5"
    maxFilesize="10MB"
    acceptedFiles="image/*,.pdf,.doc,.docx"
    :showProgress="true"
    :addRemoveLinks="true"
    dictDefaultMessage="Drop files here or click to upload"
    :validation="[
        'required' => true,
        'mimes' => 'jpg,jpeg,png,pdf',
        'max' => 10240
    ]"
    onSuccess="handleUploadSuccess"
    onError="handleUploadError"
/>
```

### Processing Uploads in Controller

```php
use DevWizard\Filex\Traits\HasFilex;

class DocumentController extends Controller
{
    use HasFilex;

    public function store(Request $request)
    {
        // Get validation rules
        $rules = $this->getValidationRules('documents', true);
        $request->validate($rules);

        // Get upload statistics
        $tempPaths = $request->input('documents', []);
        $stats = $this->getUploadStats($tempPaths);

        // Process uploaded files from temp to permanent storage
        $filePaths = $this->processFiles(
            $request,
            'documents',
            'documents/user-uploads',
            'public'
        );

        // Prepare data for storage
        $fileData = $this->prepareFileData($filePaths, ['user_id' => auth()->id()]);

        // Save file paths to your model
        Document::create([
            'user_id' => auth()->id(),
            'files' => $fileData['files'],
            'file_count' => $fileData['file_count'],
            'upload_stats' => $stats,
        ]);

        return back()->with('success',
            "Uploaded {$stats['total_files']} files ({$stats['total_size_formatted']})"
        );
    }
}
```

### HasFilex Trait Features

The `HasFilex` trait provides **21 methods** with short, clear names for complete file handling:

#### Core File Processing (9 methods)

-   `processFiles()` - Process multiple files
-   `processSingleFile()` - Process single file
-   `validateFiles()` - Validate temp files
-   `getFilesInfo()` - Get file information
-   `cleanupFiles()` - Clean up temp files
-   `getValidationRules()` - Get form validation rules
-   `prepareFileData()` - Prepare data for database
-   `handleBulkUpdate()` - Handle bulk operations
-   `getFilexService()` - Access FilexService instance

#### File Utilities (12 methods)

-   `isAllowedExtension()` - Check extension validation
-   `isAllowedMimeType()` - Check MIME type validation
-   `formatFileSize()` - Size formatting
-   `getFileIcon()` - File icons
-   `generateFileName()` - Unique names
-   `validateTempFile()` - Single validation
-   `getValidationErrors()` - Detailed errors
-   `getDisk()` - Storage disk access
-   `getTempDisk()` - Temp storage access
-   `cleanupExpired()` - Cleanup utilities
-   `moveFilesWithProgress()` - Progress tracking
-   `getUploadStats()` - Upload analytics

#### Advanced Examples

```php
// Get comprehensive upload statistics
$stats = $this->getUploadStats($tempPaths);
// Returns: file count, sizes, types, analytics

// Move files with real-time progress
$results = $this->moveFilesWithProgress($tempPaths, 'uploads', null,
    fn($current, $total, $file) => broadcast(new Progress($current, $total))
);

// Get detailed validation errors
$errors = $this->getValidationErrors($tempPaths);
// Returns: specific errors for each file

// Format file sizes
$formatted = $this->formatFileSize(1048576); // "1.00 MB"
```

### Service Usage

```php
use DevWizard\Filex\Services\FilexService;

class MyController extends Controller
{
    public function __construct(
        private FilexService $filexService
    ) {}

    public function handleUpload(Request $request)
    {
        $tempPaths = $request->input('file_paths', []);

        $finalPaths = $this->filexService->moveFiles(
            $tempPaths,
            'uploads/documents',
            'public'
        );

        // Process $finalPaths...
    }
}
```

### Facade Usage

```php
use DevWizard\Filex\Facades\Filex;

class MyController extends Controller
{
    public function handleUpload(Request $request)
    {
        $tempPaths = $request->input('file_paths', []);

        // Move files using the facade
        $finalPaths = Filex::moveFiles(
            $tempPaths,
            'uploads/documents',
            'public'
        );

        // Generate unique filename
        $uniqueName = Filex::generateFileName('document.pdf');

        // Validate temporary file
        $validation = Filex::validateTemp($tempPath, $originalName);

        // Process $finalPaths...
    }
}
```

## Configuration

The package provides extensive configuration options in `config/filex.php`. See the published config file for all available options.

### Route Configuration

You can customize the route prefix, domain, and middleware for file upload endpoints:

```php
// config/filex.php
return [
    'routes' => [
        'prefix' => env('FILEX_ROUTE_PREFIX', 'filex'),
        'domain' => env('FILEX_ROUTE_DOMAIN', null),
        'middleware' => env('FILEX_ROUTE_MIDDLEWARE', []),
    ],
    // ... other config options
];
```

Or configure via environment variables:

```env
# .env
FILEX_ROUTE_PREFIX=api/uploads
FILEX_ROUTE_DOMAIN=files.example.com
FILEX_ROUTE_MIDDLEWARE=auth,throttle:uploads
FILEX_ROUTE_NAME=uploads.
```

This allows you to:

-   Change the route prefix from `/filex/` to any custom prefix
-   Set a specific domain for file upload routes
-   Add custom middleware to protect upload routes
-   Customize the route names

## File Cleanup

The package includes automatic cleanup of temporary files:

```bash
# Manual cleanup
php artisan filex:cleanup-temp

# Dry run (see what would be cleaned)
php artisan filex:cleanup-temp --dry-run

# Force cleanup without confirmation
php artisan filex:cleanup-temp --force
```

The cleanup is automatically scheduled based on your configuration.

## Security Features

Laravel Filex includes comprehensive security features to protect your application from malicious file uploads:

### Suspicious File Detection

The package automatically scans uploaded files for potential threats:

-   **File signature validation**: Verifies file headers match declared extensions
-   **Content analysis**: Scans text files for suspicious patterns (PHP code, scripts, etc.)
-   **Filename validation**: Detects suspicious filenames and path traversal attempts
-   **Executable detection**: Identifies and blocks executable files

### Configuration

Enable or disable security features in your `.env` file:

```env
# Security settings
FILEX_SUSPICIOUS_DETECTION_ENABLED=true
FILEX_QUARANTINE_ENABLED=true
FILEX_SCAN_CONTENT=true
FILEX_VALIDATE_SIGNATURES=true
```

### Quarantine System

Suspicious files are automatically quarantined instead of being processed:

```bash
# Clean up quarantined files
php artisan filex:cleanup-temp --quarantine-only

# Clean up both temp and quarantined files
php artisan filex:cleanup-temp --include-quarantine
```

### Custom Security Patterns

You can customize detection patterns in `config/filex.php`:

```php
'security' => [
    'suspicious_filename_patterns' => [
        '/\.(php|phtml|php3|php4|php5)$/i',
        '/\.(asp|aspx|jsp|cfm)$/i',
        // Add your custom patterns
        '/\.backup$/i',
        '/malicious_pattern/i',
    ],
    'suspicious_content_patterns' => [
        '/<\?php/i',
        '/eval\s*\(/i',
        // Add your custom patterns
        '/dangerous_function\s*\(/i',
    ],
],
```

For detailed security configuration, see [SECURITY_CONFIG.md](SECURITY_CONFIG.md).

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [IQBAL HASAN](https://github.com/devwizardhq)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Localization

Laravel Filex provides comprehensive localization support with built-in translations for multiple languages.

### Publishing Language Files

To customize the language files, publish them to your application:

```bash
php artisan vendor:publish --provider="DevWizard\Filex\FilexServiceProvider" --tag="filex-lang"
```

This will publish the language files to `resources/lang/vendor/filex/`.

### Supported Languages

-   🇺🇸 **English** (en) - Default
-   🇧🇩 **Bengali** (bn) - Complete
-   🇪🇸 **Spanish** (es) - Coming soon
-   🇫🇷 **French** (fr) - Coming soon
-   🇩🇪 **German** (de) - Coming soon
-   🇨🇳 **Chinese** (zh) - Coming soon
-   🇸🇦 **Arabic** (ar) - Coming soon
-   🇷🇺 **Russian** (ru) - Coming soon
-   🇮🇳 **Hindi** (hi) - Coming soon
-   🇧🇷 **Portuguese** (pt) - Coming soon
-   🇯🇵 **Japanese** (ja) - Coming soon
-   🇮🇹 **Italian** (it) - Coming soon
-   🇹🇷 **Turkish** (tr) - Coming soon

### Customizing Messages

After publishing, you can customize any message in the language files:

```php
// resources/lang/vendor/filex/en/translations.php - UI and general messages
return [
    'ui' => [
        'drop_files' => 'Drop your files here or click to browse',
        'file_too_big' => 'File is too large (:filesize MB). Maximum size: :maxFilesize MB.',
        'invalid_file_type' => 'This file type is not allowed.',
        // ... more UI messages
    ],
    'errors' => [
        'file_not_found' => 'File not found',
        'validation_failed' => 'File validation failed',
        // ... more error messages
    ],
];

// resources/lang/vendor/filex/en/validation.php - Validation rule messages
return [
    'filex_mimes' => 'The :attribute must be a file of type: :values.',
    'filex_max' => 'The :attribute may not be greater than :max kilobytes.',
    'filex_min' => 'The :attribute must be at least :min kilobytes.',
    'filex_image' => 'The :attribute must be an image.',
    // ... more validation messages
];
```

### Creating New Language Files

To add support for a new language:

1. Create a new language directory: `resources/lang/vendor/filex/[locale]/`
2. Copy the English language files: 
   ```bash
   cp resources/lang/vendor/filex/en/translations.php resources/lang/vendor/filex/[locale]/translations.php
   cp resources/lang/vendor/filex/en/validation.php resources/lang/vendor/filex/[locale]/validation.php
   ```
3. Translate all the messages in both files
4. Set your application locale in `config/app.php` or dynamically

### Language Keys Reference

The language files contain the following key groups:

-   **`translations.php`** - UI messages, upload error messages, help text, and general error messages
-   **`validation.php`** - Validation rule messages for all Filex validation rules (filex_mimes, filex_max, etc.)

### Dynamic Language Switching

You can switch languages dynamically in your application:

```php
// In your controller or middleware
App::setLocale('bn'); // Switch to Bengali

// Or use helper
app()->setLocale('bn');
```

### Usage with Blade Components

The file upload component automatically uses the correct language based on your application's locale:

```blade
{{-- Messages will be displayed in the current locale --}}
<x-filex-uploader name="files" />
```

### Contributing Translations

We welcome contributions for new languages! To contribute:

1. Fork the repository
2. Create a new language file based on the English version
3. Translate all messages while keeping the same structure
4. Test the translations in your application
5. Submit a pull request
