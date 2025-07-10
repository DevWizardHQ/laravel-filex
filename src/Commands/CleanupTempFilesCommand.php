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
        $this->description = __('filex::translations.commands.cleanup.description');
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $quarantineOnly = $this->option('quarantine-only');
        if ($quarantineOnly) {
            $this->info(__('filex::translations.commands.cleanup.quarantine_starting'));
            return $this->handleQuarantineCleanup();
        }
        $this->info(__('filex::translations.commands.cleanup.starting'));
        if ($this->option('dry-run')) {
            $this->warn(__('filex::translations.commands.cleanup.dry_run'));
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
                    __('filex::translations.commands.cleanup.confirm', ['count' => $results['cleaned_count']])
                );
                if (!$confirmed) {
                    $this->info(__('filex::translations.commands.cleanup.cancelled'));
                    return self::SUCCESS;
                }
            }
            // Display results
            $this->displayResults($results, __('filex::translations.commands.cleanup.type_temporary'));
            // Handle quarantine cleanup (always included)
            $this->newLine();
            $this->info(__('filex::translations.commands.cleanup.quarantine_starting'));
            $quarantineResults = $this->filexService->cleanupQuarantine();
            $this->displayResults($quarantineResults, __('filex::translations.commands.cleanup.type_quarantined'));
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error(__('filex::translations.commands.cleanup.failed', ['error' => $e->getMessage()]));
            return self::FAILURE;
        }
    }

    /**
     * Handle quarantine-only cleanup
     */
    protected function handleQuarantineCleanup(): int
    {
        if ($this->option('dry-run')) {
            $this->warn(__('filex::translations.commands.cleanup.dry_run'));
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
                    __('filex::translations.commands.cleanup.confirm', ['count' => $results['cleaned_count']])
                );

                if (!$confirmed) {
                    $this->info(__('filex::translations.commands.cleanup.cancelled'));
                    return self::SUCCESS;
                }
            }

            $this->displayResults($results, __('filex::translations.commands.cleanup.type_quarantined'));
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error(__('filex::translations.commands.cleanup.failed', ['error' => $e->getMessage()]));
            return self::FAILURE;
        }
    }

    /**
     * Display dry run results
     */
    protected function displayDryRunResults(array $results, string $type = 'temporary'): void
    {
        if ($results['cleaned_count'] > 0) {
            $this->info(__('filex::translations.commands.cleanup.files_found', ['count' => $results['cleaned_count']]));

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
            $this->info(__('filex::translations.commands.cleanup.files_deleted', ['count' => $results['cleaned_count']]));

            if ($this->output->isVerbose()) {
                $this->table(
                    ['File Path', 'Status'],
                    collect($results['cleaned'])->map(function ($file) {
                        return [$file, 'Deleted'];
                    })->toArray()
                );
            }
        } else {
            $this->info(__('filex::translations.commands.cleanup.no_files'));
        }

        if ($results['error_count'] > 0) {
            $this->error(__('filex::translations.commands.cleanup.error_count', ['count' => $results['error_count']]));
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
