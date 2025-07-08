<?php

namespace DevWizard\Filex;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use DevWizard\Filex\Commands\FilexCommand;

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
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_filex_table')
            ->hasCommand(FilexCommand::class);
    }
}
