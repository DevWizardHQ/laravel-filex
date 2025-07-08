<?php

namespace DevWizard\Filex\Commands;

use Illuminate\Console\Command;

class FilexCommand extends Command
{
    public $signature = 'filex:info';

    public $description = 'Display Laravel Filex package information';

    public function handle(): int
    {
        $this->info('🚀 Laravel Filex - Modern File Upload Component');
        $this->line('');
        $this->line('Available commands:');
        $this->line('  filex:cleanup-temp    Clean up expired temporary files');
        $this->line('  filex:info           Show this information');
        $this->line('');
        $this->line('Usage:');
        $this->line('  <x-filex-uploader name="files" :multiple="true" />');
        $this->line('');
        $this->comment('For more documentation, visit: https://github.com/devwizardhq/laravel-filex');

        return 0;
    }
}
