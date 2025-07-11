# Changelog

All notable changes to `laravel-filex` will be documented in this file.

## v1.1.0 - 2025-07-11

#### Added

-   🔐 **File Visibility Control**: Added support for controlling file visibility when moving from temporary to permanent storage
    -   Files can be set as `public` (accessible by anyone with URL) or `private` (only accessible through authorized requests)
    -   Default visibility is configurable via `FILEX_DEFAULT_VISIBILITY` environment variable (defaults to `public`)
    -   Enhanced `moveFiles()` and `moveFile()` methods in FilexService to accept optional `$visibility` parameter
    -   Updated Filex facade with visibility support and convenience methods:
        -   `moveFileWithVisibility()` - Move single file with specific visibility
        -   `moveFilesWithVisibility()` - Move multiple files with specific visibility
        -   `moveFilePublic()` - Move single file as public
        -   `moveFilePrivate()` - Move single file as private
        -   `moveFilesPublic()` - Move multiple files as public
        -   `moveFilesPrivate()` - Move multiple files as private
    -   Enhanced HasFilex trait with visibility control methods:
        -   `moveFileWithVisibility()` - Move single file with specific visibility
        -   `moveFilesWithVisibility()` - Move multiple files with specific visibility
        -   `moveFilePublic()` - Convenience method for public files
        -   `moveFilePrivate()` - Convenience method for private files
        -   `moveFilesPublic()` - Convenience method for multiple public files
        -   `moveFilesPrivate()` - Convenience method for multiple private files
    -   Backward compatibility maintained - existing code continues to work without changes

#### Configuration

-   Added `storage.visibility.default` configuration option to set default file visibility
-   Environment variable `FILEX_DEFAULT_VISIBILITY` for easy deployment configuration

#### Documentation

-   Updated README with comprehensive file visibility control examples
-   Added usage examples for controllers showing public/private file handling
-   Documented all new methods and configuration options

## v1.0.0 - 2025-07-11

#### Added

-   🎉 **Initial stable release of Laravel Filex**
-   Modern, asynchronous file upload component for Laravel applications
-   Drag-and-drop file uploads with real-time progress indicators
-   Preview rendering for uploaded files
-   Chunked uploads for large files
-   Temporary file handling with finalization on form submission
-   Comprehensive validation rules for files (size, dimensions, mime types, etc.)
-   Security features including malware scanning and suspicious content detection
-   Multi-language support (English, Arabic, Bengali, German, Spanish, French, Hindi, Italian, Japanese, Portuguese, Russian, Chinese)
-   Performance monitoring and optimization tools
-   Caching system for improved performance
-   Command-line tools for maintenance and optimization
-   Extensive test suite with 126+ passing tests
-   Complete documentation and examples

#### Features

-   **File Upload Component**: Modern Blade component with async upload capabilities
-   **Validation Rules**: Custom validation rules for file uploads (FilexFile, FilexImage, FilexSize, etc.)
-   **Security**: Built-in security scanning and quarantine system
-   **Performance**: Caching, monitoring, and optimization tools
-   **Internationalization**: Support for 12 languages
-   **Commands**: CLI commands for installation, cleanup, and optimization
-   **Traits**: HasFilex trait with clean, simple API (5 essential methods: `moveFile`, `moveFiles`, validation helpers, and cleanup)
-   **Services**: Comprehensive service layer for file operations
-   **Middleware**: Security middleware for file upload protection

#### Trait Design Philosophy

-   **Clean & Focused**: HasFilex trait provides only 5 essential methods for common use cases
-   **Consistent Naming**: Trait methods match main Filex class (`moveFile`, `moveFiles`)
-   **Simple API**: No complex internal logic exposed, uses Facade pattern for consistency
-   **Easy Integration**: Designed for controllers and models with minimal learning curve

#### Technical Specifications

-   **PHP**: ^8.4
-   **Laravel**: ^11.0 || ^12.0
-   **Architecture**: Service-oriented with facades and dependency injection
-   **Testing**: 129 tests with comprehensive coverage
-   **Code Quality**: PHPStan level

**Full Changelog**: https://github.com/DevWizardHQ/laravel-filex/commits/1.0.0
