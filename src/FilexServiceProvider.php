<?php

namespace DevWizard\Filex;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use DevWizard\Filex\Commands\FilexCommand;
use DevWizard\Filex\Commands\CleanupTempFilesCommand;
use DevWizard\Filex\Commands\InstallCommand;
use DevWizard\Filex\Services\FilexService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Console\Scheduling\Schedule;

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
    }

    public function packageBooted(): void
    {
        // Register Blade component
        Blade::component('filex::components.file-uploader', 'filex-uploader');
        
        // Register Blade directive for Filex assets and routes
        Blade::directive('filexAssets', function () {
            return "<?php echo app('DevWizard\\\\Filex\\\\FilexServiceProvider')->renderFilexAssetsAndRoutes(); ?>";
        });

        // Publish assets
        $this->publishes([
            __DIR__ . '/../resources/assets/css/dropzone.min.css' => public_path('vendor/filex/css/dropzone.min.css'),
            __DIR__ . '/../resources/assets/css/filex.css' => public_path('vendor/filex/css/filex.css'),
            __DIR__ . '/../resources/assets/js/dropzone.min.js' => public_path('vendor/filex/js/dropzone.min.js'),
            __DIR__ . '/../resources/assets/js/filex.js' => public_path('vendor/filex/js/filex.js'),
        ], 'filex-assets');

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
     * Render Filex assets and routes configuration
     *
     * @return string
     */
    public function renderFilexAssetsAndRoutes()
    {
        $cssAssets = [
            asset('vendor/filex/css/dropzone.min.css'),
            asset('vendor/filex/css/filex.css')
        ];

        $jsAssets = [
            asset('vendor/filex/js/dropzone.min.js'),
            asset('vendor/filex/js/filex.js')
        ];

        $output = '';

        // Add CSS assets
        foreach ($cssAssets as $css) {
            $output .= '<link rel="stylesheet" href="' . $css . '" />' . "\n";
        }

        // Add JS assets
        foreach ($jsAssets as $js) {
            $output .= '<script src="' . $js . '"></script>' . "\n";
        }

        // Add routes configuration
        $uploadRoute = route('filex.upload.temp');
        $deleteRoute = route('filex.temp.delete', ['filename' => '__FILENAME__']);
        
        $output .= '<script>' . "\n";
        $output .= 'window.filexRoutes = {' . "\n";
        $output .= '    upload: "' . $uploadRoute . '",' . "\n";
        $output .= '    delete: "' . $deleteRoute . '"' . "\n";
        $output .= '};' . "\n";
        $output .= '</script>' . "\n";

        return $output;
    }

    /**
     * Render Filex assets (CSS and JS)
     *
     * @return string
     */
    public function renderFilexAssets()
    {
        $cssAssets = [
            asset('vendor/filex/css/dropzone.min.css'),
            asset('vendor/filex/css/filex.css')
        ];

        $jsAssets = [
            asset('vendor/filex/js/dropzone.min.js'),
            asset('vendor/filex/js/filex.js')
        ];

        $output = '';

        // Add CSS assets
        foreach ($cssAssets as $css) {
            $output .= '<link rel="stylesheet" href="' . $css . '" />' . "\n";
        }

        // Add JS assets
        foreach ($jsAssets as $js) {
            $output .= '<script src="' . $js . '"></script>' . "\n";
        }

        return $output;
    }

    /**
     * Render Filex routes configuration
     *
     * @param string $uploadRoute
     * @param string $deleteRoute
     * @return string
     */
    public function renderFilexRoutes($uploadRoute, $deleteRoute)
    {
        $output = '<script>' . "\n";
        $output .= 'window.filexRoutes = {' . "\n";
        $output .= '    upload: "' . $uploadRoute . '",' . "\n";
        $output .= '    delete: "' . $deleteRoute . '"' . "\n";
        $output .= '};' . "\n";
        $output .= '</script>' . "\n";

        return $output;
    }
}
