<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ApiCacheService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('api-cache', []);
    }

    /**
     * Remember static/catalog data. Uses Redis when available; falls back to default store.
     */
    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $fullKey = $this->config['keys'][$key] ?? 'api:' . $key;
        $ttl = $ttlSeconds > 0 ? $ttlSeconds : ($this->config['ttl']['static'] ?? 3600);

        $store = $this->getStore();
        if ($this->supportsTags($store)) {
            $tag = str_starts_with($key, 'api:') ? ($this->config['tags']['static'] ?? 'api:static') : $this->config['tags']['catalog'];
            return Cache::tags([$tag])->remember($fullKey, $ttl, $callback);
        }

        return Cache::remember($fullKey, $ttl, $callback);
    }

    /**
     * Forget a single key by name (from config keys).
     */
    public function forget(string $key): bool
    {
        $fullKey = $this->config['keys'][$key] ?? 'api:' . $key;
        $store = $this->getStore();
        if ($this->supportsTags($store)) {
            foreach ($this->config['tags'] ?? [] as $tag) {
                try {
                    Cache::tags([$tag])->forget($fullKey);
                } catch (\Throwable) {
                    // key might not be in this tag
                }
            }
        }
        return Cache::forget($fullKey);
    }

    /**
     * Flush all API static/catalog cache (by tag when using Redis).
     */
    public function flushStatic(): void
    {
        $store = $this->getStore();
        if ($this->supportsTags($store)) {
            foreach ($this->config['tags'] ?? [] as $tag) {
                try {
                    Cache::tags([$tag])->flush();
                } catch (\Throwable) {
                    // ignore
                }
            }
        } else {
            $prefix = config('cache.prefix', '');
            foreach ($this->config['keys'] ?? [] as $key) {
                Cache::forget($prefix . $key);
            }
        }
    }

    /**
     * Get TTL for static/catalog/spin.
     */
    public function ttl(string $type = 'static'): int
    {
        return (int) ($this->config['ttl'][$type] ?? 3600);
    }

    /**
     * Get cache key from config.
     */
    public function key(string $name): string
    {
        return $this->config['keys'][$name] ?? 'api:' . $name;
    }

    private function getStore(): \Illuminate\Contracts\Cache\Store
    {
        return Cache::getStore();
    }

    private function supportsTags($store): bool
    {
        return method_exists($store, 'tags');
    }
}
