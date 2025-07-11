# Laravel Filex

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devwizardhq/laravel-filex.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-filex)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/devwizardhq/laravel-filex/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/devwizardhq/laravel-filex/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/devwizardhq/laravel-filex/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/devwizardhq/laravel-filex/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/devwizardhq/laravel-filex.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-filex)

**Laravel Filex** is a powerful, modern, and enterprise-grade file upload component for Laravel applications. Built on top of Dropzone.js, it provides a seamless drag-and-drop file upload experience with advanced features including chunked uploads, real-time progress tracking, comprehensive security validation, temporary file handling, quarantine system, and intelligent file management.

## 🚀 Features

-   **🎯 Easy Integration**: Drop-in Blade component for instant file upload functionality
-   **📁 Drag & Drop**: Modern drag-and-drop interface with visual feedback and animations
-   **📊 Progress Tracking**: Real-time upload progress with visual indicators and completion states
-   **🔄 Chunked Uploads**: Handle large files with automatic chunking for better performance and reliability
-   **⏱️ Temporary Storage**: Safe temporary file handling with automatic expiration and cleanup
-   **🔒 Advanced Validation**: Multi-layer client-side and server-side validation with custom rules
-   **🛡️ Security Features**: File signature validation, threat detection, quarantine system, and malicious content scanning
-   **🎨 Highly Customizable**: Extensive configuration options, theming, and styling flexibility
-   **☁️ Cloud Ready**: Support for local, S3, and other Laravel storage drivers with streaming
-   **🌐 Multi-language Support**: Built-in localization for multiple languages (English, Bengali, with more coming)
-   **🧹 Auto Cleanup**: Scheduled cleanup of orphaned temporary files and quarantined content
-   **⚡ Performance Optimized**: Lazy loading, intelligent caching, bulk operations, and memory management
-   **🔄 Retry Logic**: Automatic retry mechanism for failed uploads with exponential backoff
-   **📱 Responsive Design**: Mobile-friendly interface that works across all devices
-   **🎛️ Rich API**: Comprehensive API for programmatic file management and operations
-   **📈 Performance Monitoring**: Built-in performance metrics and optimization tools
-   **🚨 Error Handling**: Comprehensive error handling with detailed feedback and logging

## 📋 Requirements

-   PHP 8.1+
-   Laravel 11.x, or 12.x
-   Minimum 256MB memory (recommended 512MB or higher for large files)
-   File system write permissions for temporary storage
-   Modern web browser with JavaScript support

## 📦 Installation

Install the package via Composer:

```bash
composer require devwizardhq/laravel-filex
```

### Quick Setup (Recommended)

The package automatically publishes its configuration and assets when installed. For manual installation or to republish files:

```bash
# Install with automatic asset publishing
php artisan filex:install

# Manual installation options
php artisan filex:install --force          # Force overwrite existing files
php artisan filex:install --only-config    # Only publish configuration
php artisan filex:install --only-assets    # Only publish assets
php artisan filex:install --auto          # Silent installation
```

### Asset Integration

Add the assets directive to your main layout file:

```blade
@filexAssets
```

This directive automatically includes all required CSS, JavaScript, and route configurations with performance optimizations.

## 🔧 Basic Usage

### Simple File Upload

```blade
<form method="POST" action="/upload">
    @csrf

    <x-filex-uploader name="documents" />

    <button type="submit">Submit</button>
</form>
```

### Multiple File Upload

```blade
<x-filex-uploader
    name="files"
    :multiple="true"
    :maxFiles="5"
    maxSize="10"
    accept="image/*,.pdf,.doc,.docx"
    :required="true"
/>
```

### Advanced Configuration

```blade
<x-filex-uploader
    name="gallery"
    :multiple="true"
    :maxFiles="10"
    maxSize="20"
    accept="image/*"
    placeholder="Drop your images here or click to browse"
    :showProgress="true"
    :allowPreview="true"
    :clientValidation="true"
    :showSuccessMessages="true"
    :showErrorNotifications="true"
    errorTimeout="5000"
    successTimeout="3000"
    :retries="3"
    timeout="30000"
    onSuccess="handleSuccess"
    onError="handleError"
    onComplete="handleComplete"
    onFileAdded="handleFileAdded"
    onFileRemoved="handleFileRemoved"
    :dimensions="[
        'min_width' => 100,
        'min_height' => 100,
        'max_width' => 2000,
        'max_height' => 2000
    ]"
    :debug="false"
/>
```

## 🎛️ Component Props

| Property   | Type    | Default | Description                                    |
| ---------- | ------- | ------- | ---------------------------------------------- |
| `name`     | string  | 'files' | Input name for form submission                 |
| `multiple` | boolean | false   | Allow multiple file selection                  |
| `accept`   | string  | null    | Accepted file types (MIME types or extensions) |
| `maxFiles` | integer | null    | Maximum number of files allowed                |
| `maxSize`  | integer | 10      | Maximum file size in MB                        |
| `minSize`  | integer | null    | Minimum file size in MB                        |
| `required` | boolean | false   | Make the field required                        |
| `disabled` | boolean | false   | Disable the uploader                           |
| `readonly` | boolean | false   | Make the uploader read-only                    |

### UI Customization Props

| Property                 | Type    | Default | Description                  |
| ------------------------ | ------- | ------- | ---------------------------- |
| `label`                  | string  | null    | Field label                  |
| `placeholder`            | string  | null    | Placeholder text             |
| `helpText`               | string  | null    | Help text below the field    |
| `showProgress`           | boolean | true    | Show upload progress         |
| `showFileSize`           | boolean | true    | Show file sizes              |
| `allowPreview`           | boolean | true    | Enable file previews         |
| `showSuccessMessages`    | boolean | false   | Show success notifications   |
| `showErrorNotifications` | boolean | true    | Show error notifications     |
| `errorTimeout`           | integer | 5000    | Error message timeout (ms)   |
| `successTimeout`         | integer | 3000    | Success message timeout (ms) |
| `class`                  | string  | ''      | Additional CSS classes       |
| `style`                  | string  | ''      | Inline styles                |
| `wrapperClass`           | string  | ''      | Wrapper element CSS classes  |

### Validation Props

| Property           | Type         | Default | Description                   |
| ------------------ | ------------ | ------- | ----------------------------- |
| `rules`            | array        | []      | Laravel validation rules      |
| `mimes`            | string       | null    | Allowed MIME types            |
| `extensions`       | string       | null    | Allowed file extensions       |
| `dimensions`       | array/string | null    | Image dimension constraints   |
| `clientValidation` | boolean      | true    | Enable client-side validation |

### Upload Behavior Props

| Property          | Type    | Default   | Description                        |
| ----------------- | ------- | --------- | ---------------------------------- |
| `autoProcess`     | boolean | true      | Auto-process uploaded files        |
| `parallelUploads` | integer | 2         | Number of parallel uploads         |
| `chunkSize`       | integer | 1048576   | Chunk size for large files (bytes) |
| `retries`         | integer | 3         | Number of retry attempts           |
| `timeout`         | integer | 30000     | Upload timeout (ms)                |
| `thumbnailWidth`  | integer | 120       | Thumbnail width for previews       |
| `thumbnailHeight` | integer | 120       | Thumbnail height for previews      |
| `thumbnailMethod` | string  | 'contain' | Thumbnail resize method            |

### Event Props

| Property        | Type   | Description                                       |
| --------------- | ------ | ------------------------------------------------- |
| `onSuccess`     | string | JavaScript function called on successful upload   |
| `onError`       | string | JavaScript function called on upload error        |
| `onComplete`    | string | JavaScript function called when upload completes  |
| `onFileAdded`   | string | JavaScript function called when file is added     |
| `onFileRemoved` | string | JavaScript function called when file is removed   |
| `onUpload`      | string | JavaScript function called during upload progress |

### Debug and Testing Props

| Property | Type         | Default | Description                            |
| -------- | ------------ | ------- | -------------------------------------- |
| `debug`  | boolean      | false   | Enable debug mode for detailed logging |
| `value`  | array/string | []      | Pre-populated file paths for editing   |

## 🏗️ Processing Uploads in Controllers

### Using the HasFilex Trait

The `HasFilex` trait provides a clean, simple API for file upload operations:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DevWizard\Filex\Traits\HasFilex;

class DocumentController extends Controller
{
    use HasFilex;

    public function store(Request $request)
    {
        // Validate the request with trait helper
        $request->validate([
            'title' => 'required|string',
            ...$this->getFilesValidationRules('documents', true), // Simple validation rules
        ]);

        // Upload multiple files - clean and simple
        $filePaths = $this->moveFiles($request, 'documents', 'documents/user-uploads');

        // Create your model with the processed file paths
        Document::create([
            'title' => $request->title,
            'user_id' => auth()->id(),
            'file_paths' => $filePaths,
        ]);

        return redirect()->back()->with('success', 'Documents uploaded successfully!');
    }

    public function updateAvatar(Request $request, User $user)
    {
        // Validate single file upload
        $request->validate([
            ...$this->getFileValidationRules('avatar', true),
        ]);

        // Upload single file
        $avatarPath = $this->moveFile($request, 'avatar', 'avatars');

        $user->update(['avatar' => $avatarPath]);

        return redirect()->back()->with('success', 'Avatar updated successfully!');
    }
}
```

### Available HasFilex Methods

#### Simple Upload Methods

-   `moveFile()` - Move a single file from request to permanent storage
-   `moveFiles()` - Move multiple files from request to permanent storage

#### Validation Helpers

-   `getFileValidationRules()` - Get validation rules for single file fields
-   `getFilesValidationRules()` - Get validation rules for multiple file fields

#### Cleanup

-   `cleanupTempFiles()` - Clean up temporary files if validation fails

### Using the Service Directly

```php
<?php

namespace App\Http\Controllers;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(
        private FilexService $filexService
    ) {}

    public function store(Request $request)
    {
        $tempPaths = $request->input('file_paths', []);

        // Move files to permanent storage
        $results = $this->filexService->moveFiles(
            $tempPaths,
            'uploads/documents',
            'public'
        );

        // Extract successful file paths
        $successfulPaths = $results->getSuccessfulPaths();
        $failedPaths = $results->getFailedPaths();

        // Handle the results...
    }
}
```

### Using the Facade

```php
use DevWizard\Filex\Facades\Filex;

// Move files with default visibility
$result = Filex::moveFiles($tempPaths, 'uploads', 'public');

// Move files with specific visibility
$result = Filex::moveFiles($tempPaths, 'uploads', 'public', 'private');

// Move files with convenience methods
$result = Filex::moveFilesPublic($tempPaths, 'uploads/public');
$result = Filex::moveFilesPrivate($tempPaths, 'uploads/private');

// Move single file with visibility control
$result = Filex::moveFilePrivate($tempPath, 'uploads/documents');

// Generate unique filename
$uniqueName = Filex::generateFileName('document.pdf');

// Validate temporary file
$validation = Filex::validateTemp($tempPath, $originalName);

// Clean up expired files
$cleaned = Filex::cleanup();
```

### File Visibility Control

Laravel Filex supports controlling file visibility when moving from temporary to permanent storage:

```php
use DevWizard\Filex\Traits\HasFilex;

class DocumentController extends Controller
{
    use HasFilex;

    public function storeDocument(Request $request)
    {
        // Validate file upload
        $request->validate([
            'document' => ['required', 'string', 'starts_with:temp/'],
            'is_private' => ['boolean'],
        ]);

        // Move file with user-controlled visibility
        $visibility = $request->boolean('is_private') ? 'private' : 'public';
        $documentPath = $this->moveFile($request, 'document', 'documents', null, $visibility);

        // Or use convenience methods
        if ($request->boolean('is_private')) {
            $documentPath = $this->moveFilePrivate($request, 'document', 'documents');
        } else {
            $documentPath = $this->moveFilePublic($request, 'document', 'documents');
        }

        // Save to database
        $document = Document::create([
            'name' => $request->input('name'),
            'file_path' => $documentPath,
            'is_private' => $request->boolean('is_private'),
        ]);

        return redirect()->back()->with('success', 'Document uploaded successfully!');
    }

    public function storeMultipleDocuments(Request $request)
    {
        // Validate multiple file uploads
        $request->validate([
            'documents' => ['required', 'array'],
            'documents.*' => ['required', 'string', 'starts_with:temp/'],
            'visibility' => ['required', 'in:public,private'],
        ]);

        // Move files with specified visibility
        $documentPaths = $this->moveFiles(
            $request,
            'documents',
            'documents',
            null,
            $request->input('visibility')
        );

        // Save all documents
        foreach ($documentPaths as $path) {
            Document::create([
                'file_path' => $path,
                'is_private' => $request->input('visibility') === 'private',
            ]);
        }

        return redirect()->back()->with('success', 'Documents uploaded successfully!');
    }
}
```

## 📝 Validation Rules

Laravel Filex provides custom validation rules for enhanced file validation:

### Using Custom Validation Rules

```php
use DevWizard\Filex\Rules\ValidFileUpload;
use DevWizard\Filex\Support\FilexRule;

// In your form request or controller
$request->validate([
    'documents' => ['required', 'array'],
    'documents.*' => [
        'required',
        'string',
        'starts_with:temp/',
        new ValidFileUpload(['pdf', 'doc', 'docx'], ['application/pdf'], 10 * 1024 * 1024)
    ]
]);

// Or using the FilexRule helper
$request->validate([
    'images' => ['required', 'array'],
    'images.*' => [
        'required',
        'string',
        'starts_with:temp/',
        FilexRule::image(),
        FilexRule::mimes('jpeg,png,webp'),
        FilexRule::max(5 * 1024 * 1024), // 5MB
        FilexRule::dimensions('min_width=100,min_height=100')
    ]
]);
```

### Using the FileRule Facade

```php
use DevWizard\Filex\Facades\FileRule;

// Predefined rules for common file types
$request->validate([
    'photos' => ['required', 'array'],
    'photos.*' => ['required', 'string', FileRule::forImages(5)], // 5MB max

    'documents' => ['required', 'array'],
    'documents.*' => ['required', 'string', FileRule::forDocuments(10)], // 10MB max

    'archives' => ['required', 'array'],
    'archives.*' => ['required', 'string', FileRule::forArchives(50)], // 50MB max
]);
```

### Available Validation Rules

| Rule               | Usage                            | Description                |
| ------------------ | -------------------------------- | -------------------------- |
| `filex_mimes`      | `filex_mimes:jpeg,png,pdf`       | Validate file MIME types   |
| `filex_max`        | `filex_max:2048`                 | Maximum file size in KB    |
| `filex_min`        | `filex_min:100`                  | Minimum file size in KB    |
| `filex_image`      | `filex_image`                    | Validate as image file     |
| `filex_file`       | `filex_file`                     | Basic file validation      |
| `filex_dimensions` | `filex_dimensions:min_width=100` | Image dimension validation |
| `filex_size`       | `filex_size:1024`                | Exact file size in KB      |

## ⚙️ Configuration

Laravel Filex provides extensive configuration options. Publish and customize the config file:

```bash
php artisan vendor:publish --tag="filex-config"
```

### Configuration Table

| Configuration Key                                   | Type    | Default        | Description                              |
| --------------------------------------------------- | ------- | -------------- | ---------------------------------------- |
| **Storage Settings**                                |
| `storage.disks.default`                             | string  | `'public'`     | Default storage disk for permanent files |
| `storage.disks.temp`                                | string  | `'local'`      | Storage disk for temporary files         |
| `storage.max_file_size`                             | integer | `10`           | Maximum file size in MB                  |
| `storage.temp_expiry_hours`                         | integer | `24`           | Hours before temp files expire           |
| **File Validation**                                 |
| `validation.allowed_extensions`                     | array   | `[...]`        | Array of allowed file extensions         |
| `validation.allowed_mime_types`                     | array   | `[...]`        | Array of allowed MIME types              |
| **Upload Configuration**                            |
| `upload.chunk.size`                                 | integer | `1048576`      | Chunk size for large files (bytes)       |
| `upload.chunk.max_retries`                          | integer | `3`            | Maximum retry attempts for chunks        |
| `upload.chunk.timeout`                              | integer | `30000`        | Chunk upload timeout in milliseconds     |
| **Route Configuration**                             |
| `routes.prefix`                                     | string  | `'filex'`      | Route prefix for upload endpoints        |
| `routes.domain`                                     | string  | `null`         | Domain for upload routes                 |
| `routes.middleware`                                 | array   | `[]`           | Additional middleware for routes         |
| **Performance Settings**                            |
| `performance.memory_limit`                          | string  | `'1G'`         | Memory limit for file operations         |
| `performance.time_limit`                            | integer | `600`          | Time limit for file operations (seconds) |
| `performance.parallel_uploads`                      | integer | `2`            | Number of parallel uploads               |
| `performance.chunk_threshold`                       | integer | `52428800`     | File size threshold for chunking (50MB)  |
| `performance.batch_size`                            | integer | `5`            | Batch size for bulk operations           |
| `performance.optimization.lazy_loading`             | boolean | `true`         | Enable lazy loading of assets            |
| `performance.optimization.enable_caching`           | boolean | `true`         | Enable caching for better performance    |
| `performance.optimization.cache_ttl`                | integer | `3600`         | Cache TTL in seconds                     |
| `performance.optimization.enable_compression`       | boolean | `true`         | Enable response compression              |
| `performance.optimization.max_concurrent_uploads`   | integer | `10`           | Maximum concurrent uploads               |
| **Rate Limiting**                                   |
| `performance.rate_limiting.enabled`                 | boolean | `true`         | Enable rate limiting                     |
| `performance.rate_limiting.ip_limit`                | integer | `50`           | Uploads per IP per time window           |
| `performance.rate_limiting.user_limit`              | integer | `100`          | Uploads per user per time window         |
| `performance.rate_limiting.time_window`             | integer | `3600`         | Time window in seconds                   |
| `performance.rate_limiting.suspend_time`            | integer | `3600`         | Suspend duration in seconds              |
| **Monitoring**                                      |
| `performance.monitoring.enable_metrics`             | boolean | `false`        | Enable performance metrics               |
| `performance.monitoring.log_performance`            | boolean | `false`        | Log performance data                     |
| `performance.monitoring.max_log_entries`            | integer | `1000`         | Maximum log entries to keep              |
| **Security Settings**                               |
| `security.suspicious_detection.enabled`             | boolean | `true`         | Enable suspicious file detection         |
| `security.suspicious_detection.quarantine_enabled`  | boolean | `true`         | Enable quarantine for suspicious files   |
| `security.suspicious_detection.scan_content`        | boolean | `true`         | Enable content scanning                  |
| `security.suspicious_detection.validate_signatures` | boolean | `true`         | Enable file signature validation         |
| `security.quarantine.directory`                     | string  | `'quarantine'` | Quarantine directory name                |
| `security.quarantine.retention_days`                | integer | `30`           | Days to retain quarantined files         |
| `security.quarantine.auto_cleanup`                  | boolean | `true`         | Auto cleanup quarantined files           |
| `security.suspicious_extensions`                    | array   | `[...]`        | File extensions to block/flag            |
| `security.suspicious_content_patterns`              | array   | `[...]`        | Regex patterns for content scanning      |
| `security.text_extensions_to_scan`                  | array   | `[...]`        | Text file extensions to scan             |
| **System Management**                               |
| `system.cleanup.enabled`                            | boolean | `true`         | Enable automatic cleanup                 |
| `system.cleanup.schedule`                           | string  | `'daily'`      | Cleanup schedule (hourly/daily/weekly)   |

### Environment Variables

You can configure most settings using environment variables:

```env
# Storage Configuration
FILEX_DISK=public
FILEX_TEMP_DISK=local
FILEX_MAX_SIZE=10
FILEX_TEMP_EXPIRY=24

# Route Configuration
FILEX_ROUTE_PREFIX=api/uploads
FILEX_ROUTE_DOMAIN=files.example.com
FILEX_ROUTE_MIDDLEWARE=auth,throttle:uploads

# Performance Settings
FILEX_MEMORY_LIMIT=2G
FILEX_TIME_LIMIT=900
FILEX_PARALLEL=3
FILEX_CHUNK_THRESHOLD=25165824  # 25MB
FILEX_BATCH_SIZE=10
FILEX_LAZY_LOADING=true
FILEX_ENABLE_CACHING=true
FILEX_CACHE_TTL=3600

# Rate Limiting
FILEX_RATE_LIMITING_ENABLED=true
FILEX_RATE_IP_LIMIT=50
FILEX_RATE_USER_LIMIT=100
FILEX_RATE_TIME_WINDOW=3600

# Security Settings
FILEX_SUSPICIOUS_DETECTION_ENABLED=true
FILEX_QUARANTINE_ENABLED=true
FILEX_SCAN_CONTENT=true
FILEX_VALIDATE_SIGNATURES=true
FILEX_QUARANTINE_RETENTION_DAYS=30
FILEX_QUARANTINE_AUTO_CLEANUP=true

# System Settings
FILEX_CLEANUP_ENABLED=true
FILEX_CLEANUP_SCHEDULE=daily

# Monitoring
FILEX_ENABLE_METRICS=false
FILEX_LOG_PERFORMANCE=false
FILEX_MAX_LOG_ENTRIES=1000
```

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

This allows you to:

-   Change the route prefix from `/filex/` to any custom prefix
-   Set a specific domain for file upload routes
-   Add custom middleware to protect upload routes
-   Customize the route names

## 🧹 File Cleanup

Laravel Filex automatically manages temporary files with scheduled cleanup:

### Manual Cleanup

```bash
# Clean up expired temporary files
php artisan filex:cleanup-temp

# Dry run (see what would be cleaned)
php artisan filex:cleanup-temp --dry-run

# Force cleanup without confirmation
php artisan filex:cleanup-temp --force

# Clean up quarantined files only
php artisan filex:cleanup-temp --quarantine-only

# Include both temp and quarantined files
php artisan filex:cleanup-temp --include-quarantine
```

### Automatic Cleanup

The package automatically schedules cleanup based on your configuration:

```php
// config/filex.php
'system' => [
    'cleanup' => [
        'schedule' => env('FILEX_CLEANUP_SCHEDULE', 'daily'), // hourly, daily, weekly
    ],
],
```

The cleanup is automatically scheduled based on your configuration.

## 🔒 Security Features

Laravel Filex includes comprehensive security features to protect against malicious file uploads:

### Multi-layer Validation

-   **File Extension Validation**: Check against allowed extensions
-   **MIME Type Validation**: Verify declared MIME types
-   **File Signature Validation**: Validate actual file headers/magic bytes
-   **Content Scanning**: Scan file contents for suspicious patterns

### Threat Detection

-   **Executable File Detection**: Identify and block executable files
-   **Script Content Detection**: Scan for embedded PHP, JavaScript, and other scripts
-   **Path Traversal Protection**: Prevent directory traversal attacks
-   **Suspicious Filename Detection**: Block files with suspicious names

### Quarantine System

Suspicious files are automatically quarantined instead of being processed:

```bash
# View quarantined files
php artisan filex:cleanup-temp --dry-run --quarantine-only

# Clean up quarantined files
php artisan filex:cleanup-temp --quarantine-only --force
```

### Custom Security Patterns

Add custom security patterns in your configuration:

```php
// config/filex.php
'security' => [
    'suspicious_filename_patterns' => [
        '/\.(php|phtml|php3)$/i',
        '/malicious_pattern/i',
    ],
    'suspicious_content_patterns' => [
        '/<\?php/i',
        '/eval\s*\(/i',
        '/dangerous_function\s*\(/i',
    ],
],
```

### Security Configuration

Enable or disable security features in your environment:

```env
# Security settings
FILEX_SUSPICIOUS_DETECTION_ENABLED=true
FILEX_QUARANTINE_ENABLED=true
FILEX_SCAN_CONTENT=true
FILEX_VALIDATE_SIGNATURES=true
```

## 🎨 Styling and Customization

### CSS Customization

Laravel Filex provides comprehensive CSS classes for customization:

```css
/* Override default styles */
.filex-uploader {
    border: 3px dashed #your-color;
    border-radius: 15px;
}

.filex-uploader:hover {
    border-color: #your-hover-color;
    background-color: #your-bg-color;
}

.filex-uploader .dz-message {
    color: #your-text-color;
}

/* Custom file preview styling */
.filex-uploader .dz-preview {
    background: #your-preview-bg;
    border: 1px solid #your-border;
}
```

### Component Styling

```blade
<x-filex-uploader
    name="files"
    class="my-custom-uploader"
    style="min-height: 200px;"
    wrapperClass="my-wrapper-class"
    label="Upload Files"
    helpText="Select files to upload (max 10MB each)"
    placeholder="Drag files here or click to select"
/>
```

### Custom Event Handlers

```javascript
// Advanced JavaScript integration
function handleFileSuccess(file, response) {
    console.log("File uploaded successfully:", file.name);
    console.log("Server response:", response);

    // Custom success logic
    showCustomNotification("File uploaded successfully!");
}

function handleFileError(file, message) {
    console.error("Upload failed:", file.name, message);

    // Custom error handling
    logErrorToServer(file.name, message);
}

function handleUploadProgress(file, progress) {
    console.log(`Upload progress for ${file.name}: ${progress}%`);

    // Update custom progress indicators
    updateCustomProgressBar(file.name, progress);
}
```

```blade
<x-filex-uploader
    name="files"
    onSuccess="handleFileSuccess"
    onError="handleFileError"
    onUpload="handleUploadProgress"
    :debug="true"
/>
```

### Component Helpers

Each uploader instance exposes helper functions for programmatic control:

```javascript
// Access component helpers (replace 'filex-uploader-123' with actual component ID)
const helpers = window["filex-uploader-123_helpers"];

// Get uploaded files
const uploadedFiles = helpers.getUploadedFiles();

// Get failed files
const failedFiles = helpers.getFailedFiles();

// Retry failed uploads
helpers.retryFailedUploads();

// Clear all files
helpers.clearAll();
```

## 🎯 Advanced Features

### Chunked Uploads

For large files, Laravel Filex automatically enables chunked uploads:

```blade
<x-filex-uploader
    name="large_files"
    maxSize="500"
    chunkSize="1048576"
    :retries="5"
    timeout="60000"
    :forceChunking="true"
    parallelUploads="1"
/>
```

### Progress Tracking & Events

Real-time upload progress with customizable callbacks:

```blade
<x-filex-uploader
    name="files"
    :showProgress="true"
    :showSuccessMessages="true"
    :showErrorNotifications="true"
    errorTimeout="5000"
    successTimeout="3000"
    onUpload="function(file, progress) {
        console.log('Progress:', progress + '%');
        updateCustomProgressBar(file.name, progress);
    }"
    onSuccess="function(file, response) {
        console.log('Success:', file.name);
        logUploadSuccess(file, response);
    }"
    onError="function(file, message) {
        console.log('Error:', message);
        handleUploadError(file, message);
    }"
    onComplete="function(file) {
        console.log('Upload complete:', file.name);
        finalizeUpload(file);
    }"
    onFileAdded="function(file) {
        console.log('File added:', file.name);
        validateFileBeforeUpload(file);
    }"
    onFileRemoved="function(file) {
        console.log('File removed:', file.name);
        cleanupFileReferences(file);
    }"
/>
```

### Image Processing & Validation

Built-in image validation and processing:

```blade
<x-filex-uploader
    name="images"
    accept="image/*"
    :dimensions="[
        'min_width' => 100,
        'min_height' => 100,
        'max_width' => 4000,
        'max_height' => 4000,
        'ratio' => '16:9'
    ]"
    thumbnailWidth="200"
    thumbnailHeight="150"
    thumbnailMethod="crop"
    :allowPreview="true"
    :showFileSize="true"
/>
```

### File Editing & Updates

Handle existing files in edit forms:

```blade
{{-- Editing existing files --}}
<x-filex-uploader
    name="documents"
    :value="$model->file_paths ?? []"
    :multiple="true"
    :maxFiles="5"
    placeholder="Add or replace documents"
    helpText="Existing files will be preserved unless replaced"
/>
```

### Bulk Operations

Process multiple files efficiently:

```php
// In your controller
use DevWizard\Filex\Traits\HasFilex;

class DocumentController extends Controller
{
    use HasFilex;

    public function bulkUpdate(Request $request)
    {
        $tempPaths = $request->input('documents', []);

        // Simple bulk upload
        $documentPaths = $this->moveFiles($request, 'documents', 'documents/bulk-upload');

        return response()->json([
            'success' => true,
            'uploaded' => count($documentPaths),
            'files' => $documentPaths
        ]);
    }
}
```

## 🚀 Performance Optimization

### Configuration Optimization

```bash
# Optimize package configuration and caching
php artisan filex:optimize

# Clear and rebuild caches
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### Asset Optimization

The package includes built-in performance optimizations:

-   **Lazy Loading**: Assets are loaded only when needed
-   **Asset Minification**: Compressed CSS and JavaScript files
-   **CDN Support**: Easy integration with CDN for static assets
-   **Caching**: Smart caching of validation results and file metadata

### Large File Handling

```env
# Optimize for large file uploads
FILEX_MEMORY_LIMIT=2G
FILEX_TIME_LIMIT=1800
FILEX_CHUNK_THRESHOLD=25165824  # 25MB
FILEX_PARALLEL=1  # Reduce for very large files
```

## 🔧 Commands

Laravel Filex provides several Artisan commands:

| Command              | Description                          |
| -------------------- | ------------------------------------ |
| `filex:install`      | Install and publish package assets   |
| `filex:cleanup-temp` | Clean up temporary and expired files |
| `filex:optimize`     | Optimize package performance         |
| `filex:info`         | Display package information          |

```bash
# Get package information
php artisan filex:info

# Optimize performance
php artisan filex:optimize

# Advanced cleanup options
php artisan filex:cleanup-temp --dry-run --stats
```

## 🌐 Localization

Laravel Filex supports multiple languages out of the box.

### Supported Languages

-   🇺🇸 **English** (en) - Default
-   🇧🇩 **Bengali** (bn) - Complete
-   🇪🇸 **Spanish** (es) - Complete
-   🇫🇷 **French** (fr) - Complete
-   🇩🇪 **German** (de) - Complete
-   🇸🇦 **Arabic** (ar) - Complete
-   🇨🇳 **Chinese** (zh) - Complete
-   🇷🇺 **Russian** (ru) - Complete
-   🇮🇳 **Hindi** (hi) - Complete
-   🇧🇷 **Portuguese** (pt) - Complete
-   🇯🇵 **Japanese** (ja) - Complete
-   🇮🇹 **Italian** (it) - Complete

### Publishing Language Files

```bash
php artisan vendor:publish --tag="filex-lang"
```

### Adding Custom Languages

1. Create language directory: `resources/lang/vendor/filex/[locale]/`
2. Copy English files:
    ```bash
    cp resources/lang/vendor/filex/en/* resources/lang/vendor/filex/[locale]/
    ```
3. Translate the messages
4. Set your application locale

### Dynamic Language Switching

```php
// Switch language dynamically
App::setLocale('bn');

// In Blade templates
<x-filex-uploader name="files" />
{{-- Messages will automatically use the current locale --}}
```

### Contributing Translations

We welcome contributions for new languages! To contribute:

1. Fork the repository
2. Create a new language file based on the English version
3. Translate all messages while keeping the same structure
4. Test the translations in your application
5. Submit a pull request
