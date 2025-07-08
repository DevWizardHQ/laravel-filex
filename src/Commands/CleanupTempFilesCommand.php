<?php

namespace DevWizard\Filex\Commands;

use DevWizard\Filex\Services\FilexService;
use Illuminate\Console\Command;

class CleanupTempFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filex:cleanup-temp 
                            {--force : Force cleanup without confirmation}
                            {--dry-run : Show what would be cleaned without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired temporary files from the temp upload directory';

    protected $filexService;

    /**
     * Create a new command instance.
     */
    public function __construct(FilexService $filexService)
    {
        parent::__construct();
        $this->filexService = $filexService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting temporary file cleanup...');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No files will actually be deleted');
        }

        try {
            // Get list of files that would be cleaned
            $results = $this->filexService->cleanup();

            if ($this->option('dry-run')) {
                $this->displayDryRunResults($results);
                return self::SUCCESS;
            }

            // Ask for confirmation unless force flag is used
            if (!$this->option('force') && $results['cleaned_count'] > 0) {
                $confirmed = $this->confirm(
                    "Are you sure you want to delete {$results['cleaned_count']} expired temporary files?"
                );

                if (!$confirmed) {
                    $this->info('Cleanup cancelled by user.');
                    return self::SUCCESS;
                }
            }

            // Display results
            $this->displayResults($results);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Cleanup failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Display dry run results
     */
    protected function displayDryRunResults(array $results): void
    {
        if ($results['cleaned_count'] > 0) {
            $this->info("Would clean up {$results['cleaned_count']} expired temporary files:");
            
            $this->table(
                ['File Path', 'Status'],
                collect($results['cleaned'])->map(function ($file) {
                    return [$file, 'Would be deleted'];
                })->toArray()
            );
        } else {
            $this->info('No expired temporary files found.');
        }

        if ($results['error_count'] > 0) {
            $this->warn("Would encounter {$results['error_count']} errors:");
            foreach ($results['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }
    }

    /**
     * Display actual cleanup results
     */
    protected function displayResults(array $results): void
    {
        if ($results['cleaned_count'] > 0) {
            $this->info("Successfully cleaned up {$results['cleaned_count']} expired temporary files.");
            
            if ($this->output->isVerbose()) {
                $this->table(
                    ['File Path', 'Status'],
                    collect($results['cleaned'])->map(function ($file) {
                        return [$file, 'Deleted'];
                    })->toArray()
                );
            }
        } else {
            $this->info('No expired temporary files found.');
        }

        if ($results['error_count'] > 0) {
            $this->error("Encountered {$results['error_count']} errors during cleanup:");
            foreach ($results['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        // Show summary
        $this->newLine();
        $this->info('Cleanup Summary:');
        $this->line("  Files cleaned: {$results['cleaned_count']}");
        $this->line("  Errors: {$results['error_count']}");
    }
}
