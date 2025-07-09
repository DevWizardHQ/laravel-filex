<?php

namespace DevWizard\Filex\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FilexCacheService
{
    private const CACHE_PREFIX = 'filex_';
    private const DEFAULT_TTL = 3600; // 1 hour
    
    /**
     * Get cached value with fallback
     */
    public static function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        if (!config('filex.optimization.enable_caching', true)) {
            return $callback();
        }
        
        $cacheKey = self::CACHE_PREFIX . $key;
        $ttl = $ttl ?? config('filex.optimization.cache_ttl', self::DEFAULT_TTL);
        
        try {
            return Cache::remember($cacheKey, $ttl, $callback);
        } catch (\Exception $e) {
            Log::warning('Cache operation failed, using fallback', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $callback();
        }
    }
    
    /**
     * Store value in cache
     */
    public static function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!config('filex.optimization.enable_caching', true)) {
            return false;
        }
        
        $cacheKey = self::CACHE_PREFIX . $key;
        $ttl = $ttl ?? config('filex.optimization.cache_ttl', self::DEFAULT_TTL);
        
        try {
            return Cache::put($cacheKey, $value, $ttl);
        } catch (\Exception $e) {
            Log::warning('Cache put operation failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get value from cache
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (!config('filex.optimization.enable_caching', true)) {
            return $default;
        }
        
        $cacheKey = self::CACHE_PREFIX . $key;
        
        try {
            return Cache::get($cacheKey, $default);
        } catch (\Exception $e) {
            Log::warning('Cache get operation failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }
    
    /**
     * Forget cached value
     */
    public static function forget(string $key): bool
    {
        if (!config('filex.optimization.enable_caching', true)) {
            return false;
        }
        
        $cacheKey = self::CACHE_PREFIX . $key;
        
        try {
            return Cache::forget($cacheKey);
        } catch (\Exception $e) {
            Log::warning('Cache forget operation failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Clear all Filex cache entries
     */
    public static function flush(): bool
    {
        try {
            // Get all cache keys (this is driver-dependent)
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                /** @var \Illuminate\Cache\RedisStore $store */
                $store = Cache::getStore();
                $redis = $store->connection();
                $keys = $redis->keys(self::CACHE_PREFIX . '*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            } else {
                // For other drivers, we'll use a simple approach
                // This might not be as efficient but works across all drivers
                Cache::flush();
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Cache flush operation failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get cache statistics
     */
    public static function getStats(): array
    {
        try {
            $stats = [
                'enabled' => config('filex.optimization.enable_caching', true),
                'ttl' => config('filex.optimization.cache_ttl', self::DEFAULT_TTL),
                'driver' => config('cache.default'),
                'prefix' => self::CACHE_PREFIX
            ];
            
            // Add driver-specific stats if available
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                /** @var \Illuminate\Cache\RedisStore $store */
                $store = Cache::getStore();
                $redis = $store->connection();
                $keys = $redis->keys(self::CACHE_PREFIX . '*');
                $stats['cached_keys'] = count($keys);
                $info = $redis->info('memory');
                $stats['memory_usage'] = $info['used_memory'] ?? 'unknown';
            }
            
            return $stats;
        } catch (\Exception $e) {
            Log::warning('Failed to get cache stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Cache file metadata for quick access
     */
    public static function cacheFileMetadata(string $filePath, array $metadata): bool
    {
        $key = 'file_metadata_' . md5($filePath);
        return self::put($key, $metadata, 3600); // 1 hour cache
    }
    
    /**
     * Get cached file metadata
     */
    public static function getCachedFileMetadata(string $filePath): ?array
    {
        $key = 'file_metadata_' . md5($filePath);
        return self::get($key);
    }
    
    /**
     * Cache validation results
     */
    public static function cacheValidationResult(string $fileHash, array $result): bool
    {
        $key = 'validation_' . $fileHash;
        return self::put($key, $result, 1800); // 30 minutes cache
    }
    
    /**
     * Get cached validation result
     */
    public static function getCachedValidationResult(string $fileHash): ?array
    {
        $key = 'validation_' . $fileHash;
        return self::get($key);
    }
}
