<?php

namespace DevWizard\Filex\Tests\Commands;

use DevWizard\Filex\Commands\FilexCommand;
use DevWizard\Filex\Tests\TestCase;

class FilexCommandTest extends TestCase
{
    public function test_command_can_be_instantiated()
    {
        $command = $this->app->make(FilexCommand::class);
        expect($command)->toBeInstanceOf(FilexCommand::class);
    }

    public function test_command_displays_package_information()
    {
        $this->artisan('filex:info')
            ->expectsOutput('🚀 Laravel Filex - Modern File Upload Component')
            ->expectsOutput('Available commands:')
            ->expectsTable(
                ['Command', 'Description'],
                [
                    ['filex:cleanup-temp', 'Clean up expired temporary files'],
                    ['filex:info', 'Show this information'],
                    ['filex:install', 'Install Laravel Filex and publish config'],
                    ['filex:optimize', 'Optimize Filex performance'],
                ]
            )
            ->expectsOutput('Usage:')
            ->expectsOutput('  <x-filex-uploader name="files" :multiple="true" />')
            ->assertSuccessful();
    }
}
