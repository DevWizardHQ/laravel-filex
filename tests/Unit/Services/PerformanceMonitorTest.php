<?php

namespace DevWizard\Filex\Tests\Unit\Services;

use DevWizard\Filex\Services\PerformanceMonitor;
use DevWizard\Filex\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class PerformanceMonitorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset metrics before each test
        PerformanceMonitor::clearMetrics();

        // Enable metrics by default for testing
        Config::set('filex.performance.monitoring.enable_metrics', true);
        Config::set('filex.performance.monitoring.log_performance', false);
        Config::set('filex.performance.optimization.cache_ttl', 3600);

        // Mock the cache facade to prevent DB queries
        Cache::shouldReceive('get')
            ->andReturn([])
            ->byDefault();

        Cache::shouldReceive('put')
            ->byDefault();
    }

    public function test_performance_monitor_can_be_used()
    {
        PerformanceMonitor::startTimer('test_operation');
        usleep(10000); // Sleep for 10ms
        $duration = PerformanceMonitor::endTimer('test_operation');

        expect($duration)->toBeFloat();
        expect($duration)->toBeGreaterThan(0);
    }

    public function test_metrics_are_stored()
    {
        PerformanceMonitor::startTimer('test_operation');
        usleep(10000); // Sleep for 10ms
        PerformanceMonitor::endTimer('test_operation');

        $metrics = PerformanceMonitor::getMetrics();

        expect($metrics)->toBeArray();
        expect($metrics)->not->toBeEmpty();
        expect($metrics[0]['key'])->toBe('test_operation');
        expect($metrics[0]['value'])->toBeFloat();
        expect($metrics[0]['memory_usage'])->toBeInt();
    }

    public function test_metrics_are_not_stored_when_disabled()
    {
        // Disable metrics
        Config::set('filex.performance.monitoring.enable_metrics', false);

        PerformanceMonitor::startTimer('test_operation');
        usleep(10000); // Sleep for 10ms
        $duration = PerformanceMonitor::endTimer('test_operation');

        expect($duration)->toBe(0.0);

        $metrics = PerformanceMonitor::getMetrics();
        expect($metrics)->toBeEmpty();
    }

    public function test_metrics_are_logged_when_configured()
    {
        Config::set('filex.performance.monitoring.log_performance', true);

        // Use mock instead of spy to avoid Cache calls
        Cache::spy();
        Log::spy();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Filex Performance Metric' &&
                    isset($context['key']) &&
                    $context['key'] === 'test_operation';
            });

        PerformanceMonitor::startTimer('test_operation');
        usleep(10000); // Sleep for 10ms
        PerformanceMonitor::endTimer('test_operation');
    }

    public function test_metrics_are_cached()
    {
        $cacheKey = 'filex_metrics_'.date('Y-m-d-H');

        // Override the default mock for this test
        Cache::spy();

        Cache::shouldReceive('get')
            ->once()
            ->with($cacheKey, [])
            ->andReturn([]);

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $value, $ttl) use ($cacheKey) {
                return $key === $cacheKey &&
                    is_array($value) &&
                    count($value) === 1 &&
                    $value[0]['key'] === 'test_operation';
            });

        PerformanceMonitor::startTimer('test_operation');
        usleep(10000); // Sleep for 10ms
        PerformanceMonitor::endTimer('test_operation');
    }

    public function test_get_aggregated_metrics_returns_correct_format()
    {
        $cacheKey = 'filex_metrics_'.date('Y-m-d-H');

        // Mock cached metrics
        $mockMetrics = [
            [
                'key' => 'operation_1',
                'value' => 0.1,
                'timestamp' => now()->toISOString(),
                'memory_usage' => 1000000,
                'memory_peak' => 2000000,
                'context' => [],
            ],
            [
                'key' => 'operation_1',
                'value' => 0.2,
                'timestamp' => now()->toISOString(),
                'memory_usage' => 1100000,
                'memory_peak' => 2100000,
                'context' => [],
            ],
            [
                'key' => 'operation_2',
                'value' => 0.3,
                'timestamp' => now()->toISOString(),
                'memory_usage' => 1200000,
                'memory_peak' => 2200000,
                'context' => [],
            ],
        ];

        // Override the default mock for this test
        Cache::spy();

        Cache::shouldReceive('get')
            ->once()
            ->with($cacheKey, [])
            ->andReturn($mockMetrics);

        $aggregated = PerformanceMonitor::getAggregatedMetrics();

        expect($aggregated)->toBeArray();
        expect($aggregated)->toHaveCount(2);
        expect($aggregated)->toHaveKeys(['operation_1', 'operation_2']);

        // Check operation_1 metrics
        expect($aggregated['operation_1']['count'])->toBe(2);
        expect($aggregated['operation_1']['total_time'])->toBeGreaterThanOrEqual(0.3);
        expect($aggregated['operation_1']['avg_time'])->toBeGreaterThanOrEqual(0.15);
        expect($aggregated['operation_1']['min_time'])->toBe(0.1);
        expect($aggregated['operation_1']['max_time'])->toBe(0.2);
        expect($aggregated['operation_1']['memory_usage'])->toHaveCount(2);

        // Check operation_2 metrics
        expect($aggregated['operation_2']['count'])->toBe(1);
        expect($aggregated['operation_2']['avg_time'])->toBeGreaterThanOrEqual(0.3);
    }

    public function test_metrics_can_be_cleared()
    {
        PerformanceMonitor::startTimer('test_operation');
        usleep(10000); // Sleep for 10ms
        PerformanceMonitor::endTimer('test_operation');

        // Verify metrics were stored
        $metrics = PerformanceMonitor::getMetrics();
        expect($metrics)->not->toBeEmpty();

        // Clear metrics
        PerformanceMonitor::clearMetrics();

        // Verify metrics are cleared
        $metrics = PerformanceMonitor::getMetrics();
        expect($metrics)->toBeEmpty();
    }

    public function test_direct_metric_logging()
    {
        PerformanceMonitor::logMetric('custom_metric', 0.5, ['custom' => 'value']);

        $metrics = PerformanceMonitor::getMetrics();
        expect($metrics)->not->toBeEmpty();
        expect($metrics[0]['key'])->toBe('custom_metric');
        expect($metrics[0]['value'])->toBe(0.5);
        expect($metrics[0]['context'])->toBe(['custom' => 'value']);
    }
}
