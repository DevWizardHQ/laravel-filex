<?php

namespace DevWizard\Filex\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filex:install 
                            {--force : Force the operation to run when assets already exist}
                            {--only-config : Only publish the configuration file}
                            {--only-assets : Only publish the asset files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Laravel Filex package (publish config, assets, etc.)';

    /**
     * Assets that will be published
     *
     * @var array
     */
    protected $assets = [
        'config' => [
            'path' => 'config/filex.php',
            'description' => 'Configuration file'
        ],
        'css' => [
            'public/vendor/filex/css/dropzone.min.css' => 'Dropzone CSS library',
            'public/vendor/filex/css/filex.css' => 'Filex component styles'
        ],
        'js' => [
            'public/vendor/filex/js/dropzone.min.js' => 'Dropzone JavaScript library',
            'public/vendor/filex/js/filex.js' => 'Filex component logic'
        ]
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Installing Laravel Filex...');

        // Check what needs to be published
        $force = $this->option('force');
        $onlyConfig = $this->option('only-config');
        $onlyAssets = $this->option('only-assets');

        // Determine what to publish
        $publishConfig = !$onlyAssets;
        $publishAssets = !$onlyConfig;

        $existingFiles = $this->checkExistingFiles();

        // Show what will be published
        $this->showPublishPlan($publishConfig, $publishAssets, $existingFiles);

        // Check for existing files and ask for confirmation if needed
        $relevantExistingFiles = $this->getRelevantExistingFiles($existingFiles, $publishConfig, $publishAssets);
        if (!$force && !empty($relevantExistingFiles) && !$this->confirmOverwrite($relevantExistingFiles)) {
            $this->warn('Installation cancelled.');
            return Command::FAILURE;
        }

        // Publish configuration
        if ($publishConfig) {
            $this->publishConfiguration($force);
        }

        // Publish assets
        if ($publishAssets) {
            $this->publishAssets($force);
        }

        // Show completion message
        $this->showCompletionMessage($publishConfig, $publishAssets);

        return Command::SUCCESS;
    }

    /**
     * Check for existing files
     *
     * @return array
     */
    protected function checkExistingFiles(): array
    {
        $existing = [];

        // Check config file
        if (File::exists(config_path('filex.php'))) {
            $existing['config'] = config_path('filex.php');
        }

        // Check asset files
        foreach (['css', 'js'] as $type) {
            foreach ($this->assets[$type] as $path => $description) {
                $fullPath = base_path($path);
                if (File::exists($fullPath)) {
                    $existing['assets'][] = [
                        'path' => $path,
                        'description' => $description,
                        'fullPath' => $fullPath
                    ];
                }
            }
        }

        return $existing;
    }

    /**
     * Show what will be published
     *
     * @param bool $publishConfig
     * @param bool $publishAssets
     * @param array $existingFiles
     */
    protected function showPublishPlan(bool $publishConfig, bool $publishAssets, array $existingFiles): void
    {
        if ($publishConfig) {
            $status = isset($existingFiles['config']) ? '🔄' : '✨';
            $this->line("📄 Config: config/filex.php {$status}");
        }

        if ($publishAssets) {
            $newCount = 0;
            $updateCount = 0;
            
            foreach (['css', 'js'] as $type) {
                foreach ($this->assets[$type] as $path => $description) {
                    $exists = collect($existingFiles['assets'] ?? [])->contains('path', $path);
                    if ($exists) {
                        $updateCount++;
                    } else {
                        $newCount++;
                    }
                }
            }
            
            if ($newCount > 0 && $updateCount > 0) {
                $this->line("📦 Assets: {$newCount} new, {$updateCount} updates → public/vendor/filex/");
            } elseif ($newCount > 0) {
                $this->line("📦 Assets: {$newCount} files → public/vendor/filex/");
            } else {
                $this->line("📦 Assets: {$updateCount} updates → public/vendor/filex/");
            }
        }

        $this->newLine();
    }

    /**
     * Ask user for confirmation to overwrite existing files
     *
     * @param array $existingFiles
     * @return bool
     */
    protected function confirmOverwrite(array $existingFiles): bool
    {
        $fileCount = 0;
        if (isset($existingFiles['config'])) $fileCount++;
        if (isset($existingFiles['assets'])) $fileCount += count($existingFiles['assets']);

        $this->warn("⚠️  {$fileCount} files already exist.");
        
        return $this->confirm('Overwrite existing files?', false);
    }

    /**
     * Publish the configuration file
     *
     * @param bool $force
     */
    protected function publishConfiguration(bool $force): void
    {
        try {
            $params = ['--provider' => 'DevWizard\Filex\FilexServiceProvider', '--tag' => 'filex-config'];
            
            if ($force) {
                $params['--force'] = true;
            }

            Artisan::call('vendor:publish', $params);
            $this->line('✅ Config published');
        } catch (\Exception $e) {
            $this->error("❌ Config failed: {$e->getMessage()}");
        }
    }

    /**
     * Publish the asset files
     *
     * @param bool $force
     */
    protected function publishAssets(bool $force): void
    {
        try {
            $params = ['--provider' => 'DevWizard\Filex\FilexServiceProvider', '--tag' => 'filex-assets'];
            
            if ($force) {
                $params['--force'] = true;
            }

            Artisan::call('vendor:publish', $params);
            $this->line('✅ Assets published');
        } catch (\Exception $e) {
            $this->error("❌ Assets failed: {$e->getMessage()}");
        }
    }

    /**
     * Show completion message with next steps
     *
     * @param bool $publishedConfig
     * @param bool $publishedAssets
     */
    protected function showCompletionMessage(bool $publishedConfig, bool $publishedAssets): void
    {
        $this->newLine();
        $this->info('🎉 Laravel Filex installed successfully!');
        $this->newLine();
        
        $this->line('Next steps:');
        if ($publishedConfig) {
            $this->line('• Configure settings in config/filex.php');
        }
        if ($publishedAssets) {
            $this->line('• Add @filexAssets directive to your layout');
        }
        $this->line('• Use <x-filex-uploader /> in your forms');
        $this->newLine();
        
        $this->line('Documentation: https://github.com/devwizardhq/laravel-filex');
    }

    /**
     * Get existing files relevant to what we're publishing
     *
     * @param array $existingFiles
     * @param bool $publishConfig
     * @param bool $publishAssets
     * @return array
     */
    protected function getRelevantExistingFiles(array $existingFiles, bool $publishConfig, bool $publishAssets): array
    {
        $relevant = [];
        
        if ($publishConfig && isset($existingFiles['config'])) {
            $relevant['config'] = $existingFiles['config'];
        }
        
        if ($publishAssets && isset($existingFiles['assets'])) {
            $relevant['assets'] = $existingFiles['assets'];
        }
        
        return $relevant;
    }
}
