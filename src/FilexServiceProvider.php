<?php

namespace DevWizard\Filex;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use DevWizard\Filex\Commands\FilexCommand;
use DevWizard\Filex\Commands\CleanupTempFilesCommand;
use DevWizard\Filex\Commands\InstallCommand;
use DevWizard\Filex\Commands\OptimizeCommand;
use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Services\FileRuleService;
use DevWizard\Filex\Facades\FileRule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

class FilexServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-filex')
            ->hasConfigFile('filex')
            ->hasViews()
            ->hasTranslations()
            ->hasCommands([
                FilexCommand::class,
                CleanupTempFilesCommand::class,
                InstallCommand::class,
                OptimizeCommand::class,
            ])
            ->hasRoute('web');
    }

    public function packageRegistered(): void
    {
        // Register the filex service
        $this->app->singleton(FilexService::class, function ($app) {
            return new FilexService();
        });

        // Register the main Filex class
        $this->app->singleton(Filex::class, function ($app) {
            return new Filex($app->make(FilexService::class));
        });

        // Register the FileRule service for the facade
        $this->app->singleton('filex.file-rule', function ($app) {
            return new FileRuleService();
        });
    }

    public function packageBooted(): void
    {
        // Optimize boot performance with lazy loading
        if ($this->app->runningInConsole()) {
            $this->bootConsoleFeatures();
        }

        // Register custom validation rules immediately for unit tests
        if ($this->app->runningUnitTests()) {
            $this->registerCustomValidationRules();
            $this->registerCustomValidationMessages();
            return;
        }

        // Register custom validation rules (lazy loaded for non-test environments)
        static $rulesRegistered = false;
        $this->app->resolving('validator', function ($validator) use (&$rulesRegistered) {
            if (!$rulesRegistered) {
                $this->registerCustomValidationRules();
                $this->registerCustomValidationMessages();
                $rulesRegistered = true;
            }
        });

        // Register Blade components and directives
        $this->registerBladeFeatures();

        // Publishing setup (console only)
        if ($this->app->runningInConsole()) {
            $this->setupPublishing();
        }
    }

    /**
     * Boot console-specific features
     */
    protected function bootConsoleFeatures(): void
    {
        // Add cleanup to scheduler if enabled
        if (config('filex.cleanup.enabled', true)) {
            $this->app->afterResolving(Schedule::class, function ($schedule) {
                $frequency = config('filex.cleanup.schedule', 'daily');
                $command = $schedule->command('filex:cleanup-temp --force');

                switch ($frequency) {
                    case 'hourly':
                        $command->hourly();
                        break;
                    case 'daily':
                        $command->daily();
                        break;
                    case 'weekly':
                        $command->weekly();
                        break;
                    default:
                        $command->daily();
                }
            });
        }

        // Auto-publish assets and config if they don't exist
        $this->autoPublishAssets();
    }

    /**
     * Register Blade components and directives
     */
    protected function registerBladeFeatures(): void
    {
        // Register Blade component
        Blade::component('filex::components.file-uploader', 'filex-uploader');

        // Register Blade directive for Filex assets and routes
        Blade::directive('filexAssets', function () {
            return '<?php echo view("filex::assets")->render(); ?>';
        });
    }

    /**
     * Setup publishing configuration
     */
    protected function setupPublishing(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/filex.php' => config_path('filex.php'),
        ], 'filex-config');

        // Publish assets
        $this->publishes([
            __DIR__ . '/../resources/assets/css/dropzone.min.css' => public_path('vendor/filex/css/dropzone.min.css'),
            __DIR__ . '/../resources/assets/css/filex.css' => public_path('vendor/filex/css/filex.css'),
            __DIR__ . '/../resources/assets/js/dropzone.min.js' => public_path('vendor/filex/js/dropzone.min.js'),
            __DIR__ . '/../resources/assets/js/filex.js' => public_path('vendor/filex/js/filex.js'),
        ], 'filex-assets');
    }

    /**
     * Register custom Filex validation rules with optimization
     */
    protected function registerCustomValidationRules(): void
    {
        // Cache for rule instances to avoid repeated instantiation
        static $ruleCache = [];

        $createCachedRule = function ($ruleClass, $parameters = null) use (&$ruleCache) {
            $key = $ruleClass . serialize($parameters);
            if (!isset($ruleCache[$key])) {
                $ruleCache[$key] = $parameters !== null
                    ? new $ruleClass(...(array)$parameters)
                    : new $ruleClass();
            }
            return $ruleCache[$key];
        };

        // Register filex:mimes rule
        Validator::extend('filex_mimes', function ($attribute, $value, $parameters, $validator) use ($createCachedRule) {
            if (empty($parameters)) {
                return false;
            }
            $rule = $createCachedRule(\DevWizard\Filex\Rules\FilexMimes::class, implode(',', $parameters));
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:min rule
        Validator::extend('filex_min', function ($attribute, $value, $parameters, $validator) use ($createCachedRule) {
            if (empty($parameters) || !is_numeric($parameters[0])) {
                return false;
            }
            $rule = $createCachedRule(\DevWizard\Filex\Rules\FilexMin::class, (int) $parameters[0]);
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:max rule
        Validator::extend('filex_max', function ($attribute, $value, $parameters, $validator) use ($createCachedRule) {
            if (empty($parameters) || !is_numeric($parameters[0])) {
                return false;
            }
            $rule = $createCachedRule(\DevWizard\Filex\Rules\FilexMax::class, (int) $parameters[0]);
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:dimensions rule
        Validator::extend('filex_dimensions', function ($attribute, $value, $parameters, $validator) use ($createCachedRule) {
            if (empty($parameters)) {
                return false;
            }
            $rule = $createCachedRule(\DevWizard\Filex\Rules\FilexDimensions::class, implode(',', $parameters));
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:image rule
        Validator::extend('filex_image', function ($attribute, $value, $parameters, $validator) use ($createCachedRule) {
            $rule = $createCachedRule(\DevWizard\Filex\Rules\FilexImage::class);
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:file rule
        Validator::extend('filex_file', function ($attribute, $value, $parameters, $validator) use ($createCachedRule) {
            $rule = $createCachedRule(\DevWizard\Filex\Rules\FilexFile::class);
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:size rule
        Validator::extend('filex_size', function ($attribute, $value, $parameters, $validator) use ($createCachedRule) {
            if (empty($parameters) || !is_numeric($parameters[0])) {
                return false;
            }
            $rule = $createCachedRule(\DevWizard\Filex\Rules\FilexSize::class, (int) $parameters[0]);
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:mimetypes rule
        Validator::extend('filex_mimetypes', function ($attribute, $value, $parameters, $validator) use ($createCachedRule) {
            if (empty($parameters)) {
                return false;
            }
            $rule = $createCachedRule(\DevWizard\Filex\Rules\FilexMimetypes::class, implode(',', $parameters));
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register custom validation messages and replacers
        $this->registerCustomValidationMessages();
    }

    /**
     * Register custom validation messages for Filex rules
     */
    protected function registerCustomValidationMessages(): void
    {
        // Register custom error message replacers
        Validator::replacer('filex_mimes', function ($message, $attribute, $rule, $parameters) {
            $customMessage = $this->getValidationMessage('filex_mimes');
            return str_replace([':attribute', ':values'], [$attribute, implode(', ', $parameters)], $customMessage);
        });

        Validator::replacer('filex_min', function ($message, $attribute, $rule, $parameters) {
            $customMessage = $this->getValidationMessage('filex_min');
            return str_replace([':attribute', ':min'], [$attribute, $parameters[0]], $customMessage);
        });

        Validator::replacer('filex_max', function ($message, $attribute, $rule, $parameters) {
            $customMessage = $this->getValidationMessage('filex_max');
            return str_replace([':attribute', ':max'], [$attribute, $parameters[0]], $customMessage);
        });

        Validator::replacer('filex_size', function ($message, $attribute, $rule, $parameters) {
            $customMessage = $this->getValidationMessage('filex_size');
            return str_replace([':attribute', ':size'], [$attribute, $parameters[0]], $customMessage);
        });

        Validator::replacer('filex_mimetypes', function ($message, $attribute, $rule, $parameters) {
            $customMessage = $this->getValidationMessage('filex_mimetypes');
            return str_replace([':attribute', ':values'], [$attribute, implode(', ', $parameters)], $customMessage);
        });

        Validator::replacer('filex_dimensions', function ($message, $attribute, $rule, $parameters) {
            $customMessage = $this->getValidationMessage('filex_dimensions');
            return str_replace(':attribute', $attribute, $customMessage);
        });

        Validator::replacer('filex_image', function ($message, $attribute, $rule, $parameters) {
            $customMessage = $this->getValidationMessage('filex_image');
            return str_replace(':attribute', $attribute, $customMessage);
        });

        Validator::replacer('filex_file', function ($message, $attribute, $rule, $parameters) {
            $customMessage = $this->getValidationMessage('filex_file');
            return str_replace(':attribute', $attribute, $customMessage);
        });
    }

    /**
     * Get validation message with fallback
     */
    protected function getValidationMessage(string $key): string
    {
        $message = trans("filex::validation.{$key}");
        
        // If the translation key is not found (returns the key itself), use a default message
        if ($message === "filex::validation.{$key}") {
            $defaults = [
                'filex_mimes' => 'The :attribute must be a file of type: :values.',
                'filex_mimetypes' => 'The :attribute file must be of type: :values.',
                'filex_min' => 'The :attribute must be at least :min kilobytes.',
                'filex_max' => 'The :attribute may not be greater than :max kilobytes.',
                'filex_size' => 'The :attribute must be exactly :size kilobytes.',
                'filex_dimensions' => 'The :attribute has invalid image dimensions.',
                'filex_image' => 'The :attribute must be an image.',
                'filex_file' => 'The :attribute must be a valid file upload.',
                'temp_file' => 'The :attribute must be a valid Filex temp file.',
                'file_not_found_or_expired' => 'The :attribute file not found or expired.',
                'file_not_found' => 'The :attribute file not found.',
                'file_not_readable' => 'The :attribute file is not readable.',
                'must_be_image' => 'The :attribute must be an image.',
                'invalid_image_dimensions' => 'The :attribute has invalid image dimensions.',
                'min_width' => 'The :attribute has invalid image dimensions. Minimum width is :value px.',
                'max_width' => 'The :attribute has invalid image dimensions. Maximum width is :value px.',
                'min_height' => 'The :attribute has invalid image dimensions. Minimum height is :value px.',
                'max_height' => 'The :attribute has invalid image dimensions. Maximum height is :value px.',
                'exact_width' => 'The :attribute has invalid image dimensions. Width must be exactly :value px.',
                'exact_height' => 'The :attribute has invalid image dimensions. Height must be exactly :value px.',
                'aspect_ratio' => 'The :attribute has invalid image dimensions. Aspect ratio must be :value.',
                'file_size_exact' => 'The :attribute must be exactly :size.',
                'file_too_large' => 'The :attribute may not be greater than :max.',
                'file_too_small' => 'The :attribute must be at least :min.',
                'invalid_mime_type' => 'The :attribute must be a file of type: :values.',
                'invalid_mimetypes' => 'The :attribute file must be of type: :values.',
                'invalid_upload' => 'The :attribute must be a valid file upload.',
                'file_content_mismatch' => 'The :attribute file content does not match expected type.',
                'file_signature_validation_failed' => 'The :attribute file signature validation failed.',
                'file_content_validation_failed' => 'The :attribute file content validation failed.',
                'file_security_validation_failed' => 'The :attribute file failed security validation.',
                'file_validation_failed' => 'The :attribute file validation failed.',
                'invalid_file_path' => 'Invalid file path for :attribute. File deletion not allowed.',
            ];
            
            return $defaults[$key] ?? "The :attribute field is invalid.";
        }
        
        return $message;
    }

    /**
     * Auto-publish assets and config if they don't exist
     */
    protected function autoPublishAssets(): void
    {
        if (!file_exists(public_path('vendor/filex/css/filex.css'))) {
            Artisan::call('filex:install', [
                '--only-assets' => true,
                '--auto' => true,
                '--force' => true,
            ]);
        }

        if (!file_exists(config_path('filex.php'))) {
            Artisan::call('filex:install', [
                '--only-config' => true,
                '--auto' => true,
                '--force' => true,
            ]);
        }
    }
}
