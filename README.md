# laravel-filex is a powerful and reusable Blade component that brings modern, asynchronous file uploads to Laravel applications. It supports features like drag-and-drop uploads, real-time progress indicators, preview rendering, chunked upload for large files, and temporary file handling with finalization on form submission.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devwizardhq/laravel-filex.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-filex)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/devwizardhq/laravel-filex/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/devwizardhq/laravel-filex/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/devwizardhq/laravel-filex/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/devwizardhq/laravel-filex/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/devwizardhq/laravel-filex.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-filex)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require devwizardhq/laravel-filex
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-filex-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-filex-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-filex-views"
```

## Usage

```php
$filex = new DevWizard\Filex();
echo $filex->echoPhrase('Hello, DevWizard!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [IQBAL HASAN](https://github.com/iqbalhasandev)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
