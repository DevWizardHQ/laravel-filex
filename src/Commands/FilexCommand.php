<?php

namespace DevWizard\Filex\Commands;

use Illuminate\Console\Command;

class FilexCommand extends Command
{
    public $signature = 'laravel-filex';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
