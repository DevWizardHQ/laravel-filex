<?php

namespace DevWizard\Filex\Tests\Unit\Services;

use DevWizard\Filex\Services\FilexCacheService;
use DevWizard\Filex\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class FilexCacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Enable caching by default for tests
        Config::set('filex.performance.optimization.enable_caching', true);
        Config::set('filex.performance.optimization.cache_ttl', 60);

        // Mock cache to prevent database interactions
        Cache::spy();
    }

    public function test_remember_executes_callback_and_caches_result()
    {
        $key = 'test_key';
        $expectedValue = 'cached_value';
        $cacheCalled = false;

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($cacheKey, $ttl, $callback) use ($key, &$cacheCalled) {
                $cacheCalled = true;
                expect($cacheKey)->toBe('filex_'.$key);
                expect($ttl)->toBe(60);

                return true;
            })
            ->andReturn($expectedValue);

        $result = FilexCacheService::remember($key, function () use ($expectedValue) {
            return $expectedValue;
        });

        expect($cacheCalled)->toBeTrue();
        expect($result)->toBe($expectedValue);
    }

    public function test_remember_uses_fallback_when_cache_fails()
    {
        $key = 'test_key';
        $expectedValue = 'callback_value';

        Cache::shouldReceive('remember')
            ->once()
            ->andThrow(new \Exception('Cache error'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use ($key) {
                expect($message)->toBe('Cache operation failed, using fallback');
                expect($context['key'])->toBe($key);

                return true;
            });

        $result = FilexCacheService::remember($key, function () use ($expectedValue) {
            return $expectedValue;
        });

        expect($result)->toBe($expectedValue);
    }

    public function test_put_stores_value_in_cache()
    {
        $key = 'test_key';
        $value = 'test_value';

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($cacheKey, $cacheValue, $ttl) use ($key, $value) {
                expect($cacheKey)->toBe('filex_'.$key);
                expect($cacheValue)->toBe($value);
                expect($ttl)->toBe(60);

                return true;
            })
            ->andReturn(true);

        $result = FilexCacheService::put($key, $value);

        expect($result)->toBeTrue();
    }

    public function test_get_retrieves_value_from_cache()
    {
        $key = 'test_key';
        $value = 'test_value';
        $default = 'default_value';

        Cache::shouldReceive('get')
            ->once()
            ->withArgs(function ($cacheKey, $defaultValue) use ($key, $default) {
                expect($cacheKey)->toBe('filex_'.$key);
                expect($defaultValue)->toBe($default);

                return true;
            })
            ->andReturn($value);

        $result = FilexCacheService::get($key, $default);

        expect($result)->toBe($value);
    }

    public function test_forget_removes_value_from_cache()
    {
        $key = 'test_key';

        Cache::shouldReceive('forget')
            ->once()
            ->withArgs(function ($cacheKey) use ($key) {
                expect($cacheKey)->toBe('filex_'.$key);

                return true;
            })
            ->andReturn(true);

        $result = FilexCacheService::forget($key);

        expect($result)->toBeTrue();
    }

    public function test_forget_handles_exceptions()
    {
        $key = 'test_key';

        Cache::shouldReceive('forget')
            ->once()
            ->andThrow(new \Exception('Cache error'));

        Log::shouldReceive('warning')
            ->once();

        $result = FilexCacheService::forget($key);

        expect($result)->toBeFalse();
    }

    public function test_cache_is_disabled_when_config_is_off()
    {
        Config::set('filex.performance.optimization.enable_caching', false);

        // Remember should just execute callback
        $callbackExecuted = false;
        $result = FilexCacheService::remember('key', function () use (&$callbackExecuted) {
            $callbackExecuted = true;

            return 'value';
        });

        expect($callbackExecuted)->toBeTrue();
        expect($result)->toBe('value');

        // Put should return false
        expect(FilexCacheService::put('key', 'value'))->toBeFalse();

        // Get should return default
        expect(FilexCacheService::get('key', 'default'))->toBe('default');

        // Forget should return false
        expect(FilexCacheService::forget('key'))->toBeFalse();

        // Verify no cache interactions occurred
        Cache::shouldNotHaveReceived('remember');
        Cache::shouldNotHaveReceived('put');
        Cache::shouldNotHaveReceived('get');
        Cache::shouldNotHaveReceived('forget');
    }

    public function test_cache_file_metadata_stores_correctly()
    {
        $filePath = '/path/to/file.jpg';
        $metadata = ['size' => 1024, 'mime' => 'image/jpeg'];

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($cacheKey, $cacheValue, $ttl) use ($metadata) {
                expect($cacheValue)->toBe($metadata);
                expect($ttl)->toBe(3600);

                return true;
            })
            ->andReturn(true);

        $result = FilexCacheService::cacheFileMetadata($filePath, $metadata);
        expect($result)->toBeTrue();
    }

    public function test_get_cached_file_metadata_returns_correct_data()
    {
        $filePath = '/path/to/file.jpg';
        $metadata = ['size' => 1024, 'mime' => 'image/jpeg'];

        Cache::shouldReceive('get')
            ->once()
            ->andReturnUsing(function () use ($metadata) {
                return $metadata;
            });

        $result = FilexCacheService::getCachedFileMetadata($filePath);
        expect($result)->toBe($metadata);
    }

    public function test_cache_validation_result_stores_correctly()
    {
        $fileHash = 'abc123';
        $result = ['valid' => true, 'message' => null];

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($cacheKey, $cacheValue, $ttl) use ($result) {
                expect($cacheValue)->toBe($result);
                expect($ttl)->toBe(1800);

                return true;
            })
            ->andReturn(true);

        $success = FilexCacheService::cacheValidationResult($fileHash, $result);
        expect($success)->toBeTrue();
    }
}
