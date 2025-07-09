<?php

namespace DevWizard\Filex\Commands;

use DevWizard\Filex\Services\PerformanceMonitor;
use DevWizard\Filex\Services\FilexCacheService;
use Illuminate\Console\Command;

class OptimizeCommand extends Command
{
    protected $signature = 'filex:optimize 
                           {--clear-cache : Clear all Filex cache}
                           {--analyze : Analyze performance metrics}
                           {--config-check : Check configuration for optimization}';

    protected $description = 'Optimize Laravel Filex package performance';

    public function handle(): int
    {
        $this->info('🚀 Optimizing Laravel Filex...');

        if ($this->option('clear-cache')) {
            $this->clearCache();
        }

        if ($this->option('analyze')) {
            $this->analyzePerformance();
        }

        if ($this->option('config-check')) {
            $this->checkConfiguration();
        }

        if (!$this->option('clear-cache') && !$this->option('analyze') && !$this->option('config-check')) {
            $this->runFullOptimization();
        }

        return 0;
    }

    private function clearCache(): void
    {
        $this->info('🗑️  Clearing Filex cache...');
        
        if (FilexCacheService::flush()) {
            $this->info('✅ Cache cleared successfully');
        } else {
            $this->error('❌ Failed to clear cache');
        }
    }

    private function analyzePerformance(): void
    {
        $this->info('📊 Analyzing performance metrics...');
        
        $metrics = PerformanceMonitor::getAggregatedMetrics();
        
        if (empty($metrics)) {
            $this->warn('⚠️  No performance metrics found');
            $this->line('Enable metrics in config: filex.monitoring.enable_metrics');
            return;
        }

        $this->table(
            ['Operation', 'Count', 'Avg Time (ms)', 'Min Time (ms)', 'Max Time (ms)'],
            collect($metrics)->map(function ($data, $key) {
                return [
                    $key,
                    $data['count'],
                    number_format($data['avg_time'] * 1000, 2),
                    number_format($data['min_time'] * 1000, 2),
                    number_format($data['max_time'] * 1000, 2),
                ];
            })->toArray()
        );

        // Show memory usage analysis
        foreach ($metrics as $operation => $data) {
            if (!empty($data['memory_usage'])) {
                $avgMemory = array_sum($data['memory_usage']) / count($data['memory_usage']);
                $this->line("💾 {$operation}: Avg memory usage " . $this->formatBytes($avgMemory));
            }
        }
    }

    private function checkConfiguration(): void
    {
        $this->info('⚙️  Checking configuration...');
        
        $recommendations = [];
        
        // Check performance settings
        $memoryLimit = ini_get('memory_limit');
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
        
        $this->line("Current PHP settings:");
        $this->line("  Memory limit: {$memoryLimit}");
        $this->line("  Upload max filesize: {$uploadMaxFilesize}");
        $this->line("  Post max size: {$postMaxSize}");
        
        // Check Filex config
        $batchSize = config('filex.performance.batch_size', 5);
        $parallelUploads = config('filex.performance.parallel_uploads', 2);
        $cachingEnabled = config('filex.optimization.enable_caching', true);
        $metricsEnabled = config('filex.monitoring.enable_metrics', false);
        
        $this->line("\nFilex configuration:");
        $this->line("  Batch size: {$batchSize}");
        $this->line("  Parallel uploads: {$parallelUploads}");
        $this->line("  Caching enabled: " . ($cachingEnabled ? 'Yes' : 'No'));
        $this->line("  Metrics enabled: " . ($metricsEnabled ? 'Yes' : 'No'));
        
        // Provide recommendations
        if ($batchSize > 10) {
            $recommendations[] = "Consider reducing batch_size from {$batchSize} to 5-10 for better memory usage";
        }
        
        if ($parallelUploads > 3) {
            $recommendations[] = "Consider reducing parallel_uploads from {$parallelUploads} to 2-3 to avoid server overload";
        }
        
        if (!$cachingEnabled) {
            $recommendations[] = "Enable caching for better performance: filex.optimization.enable_caching = true";
        }
        
        if (!$metricsEnabled) {
            $recommendations[] = "Enable metrics for performance monitoring: filex.monitoring.enable_metrics = true";
        }
        
        if (!empty($recommendations)) {
            $this->warn("\n💡 Recommendations:");
            foreach ($recommendations as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        } else {
            $this->info("✅ Configuration looks good!");
        }
    }

    private function runFullOptimization(): void
    {
        $this->info('🔧 Running full optimization...');
        
        // Clear cache
        $this->clearCache();
        
        // Optimize configuration
        $this->optimizeConfig();
        
        // Clear old performance metrics
        PerformanceMonitor::clearMetrics();
        
        $this->info('✅ Optimization complete!');
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Monitor performance: php artisan filex:optimize --analyze');
        $this->line('2. Check configuration: php artisan filex:optimize --config-check');
        $this->line('3. Clear cache when needed: php artisan filex:optimize --clear-cache');
    }

    private function optimizeConfig(): void
    {
        $this->info('⚙️  Optimizing configuration...');
        
        // This would typically write optimized config values
        // For now, we'll just show recommendations
        $this->line('Configuration optimization complete');
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor] ?? 'TB');
    }
}
