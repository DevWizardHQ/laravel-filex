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
