<?php

namespace DevWizard\Filex\Services;

use DevWizard\Filex\Support\ByteHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PerformanceMonitor
{
    private static array $metrics = [];

    private static array $timers = [];

    /**
     * Start performance timer
     */
    public static function startTimer(string $key): void
    {
        if (config('filex.performance.monitoring.enable_metrics', false)) {
            self::$timers[$key] = microtime(true);
        }
    }

    /**
     * End performance timer and log result
     */
    public static function endTimer(string $key, array $context = []): float
    {
        if (! config('filex.performance.monitoring.enable_metrics', false)) {
            return 0.0;
        }

        if (! isset(self::$timers[$key])) {
            return 0.0;
        }

        $duration = microtime(true) - self::$timers[$key];
        unset(self::$timers[$key]);

        self::logMetric($key, $duration, $context);

        return $duration;
    }

    /**
     * Log performance metric
     */
    public static function logMetric(string $key, float $value, array $context = []): void
    {
        if (! config('filex.performance.monitoring.enable_metrics', false)) {
            return;
        }

        $metric = [
            'key' => $key,
            'value' => $value,
            'timestamp' => now()->toISOString(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'context' => $context,
        ];

        self::$metrics[] = $metric;

        // Log performance if enabled
        if (config('filex.performance.monitoring.log_performance', false)) {
            Log::info('Filex Performance Metric', $metric);
        }

        // Store in cache for analytics
        $cacheKey = 'filex_metrics_'.date('Y-m-d-H');
        $cached = Cache::get($cacheKey, []);
        $cached[] = $metric;

        // Limit cache size
        $maxEntries = config('filex.performance.monitoring.max_log_entries', 1000);
        if (count($cached) > $maxEntries) {
            $cached = array_slice($cached, -$maxEntries);
        }

        Cache::put($cacheKey, $cached, config('filex.performance.optimization.cache_ttl', 3600));
    }

    /**
     * Get performance metrics
     */
    public static function getMetrics(): array
    {
        return self::$metrics;
    }

    /**
     * Clear stored metrics
     */
    public static function clearMetrics(): void
    {
        self::$metrics = [];
        self::$timers = [];
    }

    /**
     * Get aggregated performance data
     */
    public static function getAggregatedMetrics(): array
    {
        $cacheKey = 'filex_metrics_'.date('Y-m-d-H');
        $metrics = Cache::get($cacheKey, []);

        if (empty($metrics)) {
            return [];
        }

        $aggregated = [];

        foreach ($metrics as $metric) {
            $key = $metric['key'];

            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'count' => 0,
                    'total_time' => 0,
                    'avg_time' => 0,
                    'min_time' => PHP_FLOAT_MAX,
                    'max_time' => 0,
                    'memory_usage' => [],
                ];
            }

            $aggregated[$key]['count']++;
            $aggregated[$key]['total_time'] += $metric['value'];
            $aggregated[$key]['avg_time'] = $aggregated[$key]['total_time'] / $aggregated[$key]['count'];
            $aggregated[$key]['min_time'] = min($aggregated[$key]['min_time'], $metric['value']);
            $aggregated[$key]['max_time'] = max($aggregated[$key]['max_time'], $metric['value']);
            $aggregated[$key]['memory_usage'][] = $metric['memory_usage'];
        }

        return $aggregated;
    }

    /**
     * Monitor memory usage
     */
    public static function checkMemoryUsage(string $context = ''): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ByteHelper::convertToBytes(ini_get('memory_limit'));
        $memoryPercent = ($memoryUsage / $memoryLimit) * 100;

        if ($memoryPercent > 80) {
            Log::warning('High memory usage detected', [
                'context' => $context,
                'memory_usage' => $memoryUsage,
                'memory_limit' => $memoryLimit,
                'memory_percent' => $memoryPercent,
            ]);

            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }
}
