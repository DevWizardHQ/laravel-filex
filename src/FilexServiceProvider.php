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
        // Register custom validation rules
        $this->registerCustomValidationRules();
        
        // Register Blade component
        Blade::component('filex::components.file-uploader', 'filex-uploader');
        
        // Register Blade directive for Filex assets and routes
        Blade::directive('filexAssets', function () {
            return '<?php echo view("filex::assets")->render(); ?>';
        });

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

        // Auto-publish assets and config if running in console and they don't exist
        if ($this->app->runningInConsole()) {
            $this->autoPublishAssets();
        }

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
    }

    /**
     * Register custom Filex validation rules
     */
    protected function registerCustomValidationRules(): void
    {
        // Register filex:mimes rule
        Validator::extend('filex_mimes', function ($attribute, $value, $parameters, $validator) {
            if (empty($parameters)) {
                return false;
            }
            $rule = new \DevWizard\Filex\Rules\FilexMimes(implode(',', $parameters));
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:min rule
        Validator::extend('filex_min', function ($attribute, $value, $parameters, $validator) {
            if (empty($parameters) || !is_numeric($parameters[0])) {
                return false;
            }
            $rule = new \DevWizard\Filex\Rules\FilexMin((int) $parameters[0]);
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:max rule
        Validator::extend('filex_max', function ($attribute, $value, $parameters, $validator) {
            if (empty($parameters) || !is_numeric($parameters[0])) {
                return false;
            }
            $rule = new \DevWizard\Filex\Rules\FilexMax((int) $parameters[0]);
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:dimensions rule
        Validator::extend('filex_dimensions', function ($attribute, $value, $parameters, $validator) {
            if (empty($parameters)) {
                return false;
            }
            $rule = new \DevWizard\Filex\Rules\FilexDimensions(implode(',', $parameters));
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:image rule
        Validator::extend('filex_image', function ($attribute, $value, $parameters, $validator) {
            $rule = new \DevWizard\Filex\Rules\FilexImage();
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:file rule
        Validator::extend('filex_file', function ($attribute, $value, $parameters, $validator) {
            $rule = new \DevWizard\Filex\Rules\FilexFile();
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:size rule
        Validator::extend('filex_size', function ($attribute, $value, $parameters, $validator) {
            if (empty($parameters) || !is_numeric($parameters[0])) {
                return false;
            }
            $rule = new \DevWizard\Filex\Rules\FilexSize((int) $parameters[0]);
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register filex:mimetypes rule
        Validator::extend('filex_mimetypes', function ($attribute, $value, $parameters, $validator) {
            if (empty($parameters)) {
                return false;
            }
            $rule = new \DevWizard\Filex\Rules\FilexMimetypes(implode(',', $parameters));
            $passes = true;
            $rule->validate($attribute, $value, function ($message) use (&$passes) {
                $passes = false;
            });
            return $passes;
        });

        // Register error messages
        Validator::replacer('filex_mimes', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':values', implode(', ', $parameters), $message);
        });

        Validator::replacer('filex_min', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':min', $parameters[0], $message);
        });

        Validator::replacer('filex_max', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':max', $parameters[0], $message);
        });

        Validator::replacer('filex_size', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':size', $parameters[0], $message);
        });

        Validator::replacer('filex_mimetypes', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':values', implode(', ', $parameters), $message);
        });
    }

    /**
     * Auto-publish assets and config if they don't exist
     */
    protected function autoPublishAssets(): void
    {
        // Check if config file exists
        if (!file_exists(config_path('filex.php')) || !file_exists(public_path('vendor/filex/css/filex.css'))) {
            Artisan::call('filex:install', [
                '--auto' => true,
                '--force' => false,
            ]);
        }
    }
}
