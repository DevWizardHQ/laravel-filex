<?php

namespace DevWizard\Filex;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use DevWizard\Filex\Commands\FilexCommand;
use DevWizard\Filex\Commands\CleanupTempFilesCommand;
use DevWizard\Filex\Services\FilexService;
use DevWizard\Filex\Helpers\FilexBladeHelpers;
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
        // Register Blade directives
        FilexBladeHelpers::register();

        // Register Blade component
        Blade::component('filex::components.file-uploader', 'filex-uploader');

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
}
