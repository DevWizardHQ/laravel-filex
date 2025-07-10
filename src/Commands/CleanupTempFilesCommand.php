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
                            {--dry-run : Show what would be cleaned without actually deleting}
                            {--quarantine-only : Only clean quarantined files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired temporary files and quarantined files';
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
        $quarantineOnly = $this->option('quarantine-only');
        if ($quarantineOnly) {
            $this->info('Starting quarantine cleanup...');
            return $this->handleQuarantineCleanup();
        }
        $this->info('Starting temporary file cleanup...');
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE: No files will actually be deleted.');
        }
        try {
            // Get list of files that would be cleaned
            $results = $this->filexService->cleanup();
            if ($this->option('dry-run')) {
                $this->displayDryRunResults($results, 'temporary');
                $quarantineResults = $this->filexService->cleanupQuarantine();
                $this->displayDryRunResults($quarantineResults, 'quarantined');
                return self::SUCCESS;
            }
            // Ask for confirmation unless force flag is used
            if (!$this->option('force') && $results['cleaned_count'] > 0) {
                $confirmed = $this->confirm(
                    "Are you sure you want to delete {$results['cleaned_count']} temporary files?"
                );
                if (!$confirmed) {
                    $this->info('Cleanup cancelled.');
                    return self::SUCCESS;
                }
            }
            // Display results
            $this->displayResults($results, 'temporary files');
            // Handle quarantine cleanup (always included)
            $this->newLine();
            $this->info('Starting quarantine cleanup...');
            $quarantineResults = $this->filexService->cleanupQuarantine();
            $this->displayResults($quarantineResults, 'quarantined files');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Handle quarantine-only cleanup
     */
    protected function handleQuarantineCleanup(): int
    {
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE: No files will actually be deleted.');
        }

        try {
            $results = $this->filexService->cleanupQuarantine();

            if ($this->option('dry-run')) {
                $this->displayDryRunResults($results, 'quarantined');
                return self::SUCCESS;
            }

            // Ask for confirmation unless force flag is used
            if (!$this->option('force') && $results['cleaned_count'] > 0) {
                $confirmed = $this->confirm(
                    "Are you sure you want to delete {$results['cleaned_count']} quarantined files?"
                );

                if (!$confirmed) {
                    $this->info('Cleanup cancelled.');
                    return self::SUCCESS;
                }
            }

            $this->displayResults($results, 'quarantined files');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Display dry run results
     */
    protected function displayDryRunResults(array $results, string $type = 'temporary'): void
    {
        if ($results['cleaned_count'] > 0) {
            $this->info("Found {$results['cleaned_count']} expired {$type} files that would be deleted:");

            $this->table(
                ['File Path', 'Status'],
                collect($results['cleaned'])->map(function ($file) {
                    return [$file, 'Would be deleted'];
                })->toArray()
            );
        } else {
            $this->info("No expired {$type} files found.");
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
    protected function displayResults(array $results, string $type = 'Files'): void
    {
        if ($results['cleaned_count'] > 0) {
            $this->info("Successfully deleted {$results['cleaned_count']} {$type}.");

            if ($this->output->isVerbose()) {
                $this->table(
                    ['File Path', 'Status'],
                    collect($results['cleaned'])->map(function ($file) {
                        return [$file, 'Deleted'];
                    })->toArray()
                );
            }
        } else {
            if ($type === 'temporary files') {
                $this->info('No expired Temporary Files found.');
            } elseif ($type === 'quarantined files') {
                $this->info('No expired Quarantined Files found.');
            } else {
                $this->info("No expired {$type} found to clean up.");
            }
        }

        if ($results['error_count'] > 0) {
            $this->error("Encountered {$results['error_count']} errors during cleanup:");
            foreach ($results['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        // Show summary
        $this->newLine();
        $this->info("Cleanup Summary ({$type}):");
        $this->line("  Files cleaned: {$results['cleaned_count']}");
        $this->line("  Errors: {$results['error_count']}");
    }
}
