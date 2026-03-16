<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ApiCacheService
{
    private const ENCODED_PAYLOAD_PREFIX = 'apicache:';
    private const MAX_CACHE_PAYLOAD_BYTES = 1048576;

    private array $config;

    public function __construct()
    {
        $this->config = config('api-cache', []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $normalizedKey = $this->key($key);

        try {
            $value = $this->repository()->get($normalizedKey, $default);
            if ($value === null) {
                $this->incrementMetricCounter('cache_misses');
                $this->metric('CACHE MISS', $normalizedKey);
                return $default;
            }

            $this->incrementMetricCounter('cache_hits');
            $this->metric('CACHE HIT', $normalizedKey);

            return $this->decodeStoredValue($value, $default);
        } catch (\Throwable $e) {
            $this->metric('CACHE GET FAILED', $normalizedKey, ['error' => $e->getMessage()]);
            return $default;
        }
    }

    public function set(string $key, mixed $value, int $ttlSeconds): bool
    {
        $normalizedKey = $this->key($key);

        try {
            [$shouldCache, $payload, $meta] = $this->prepareStoredPayload($value);
            if (!$shouldCache) {
                $this->metric('CACHE SKIP OVERSIZED', $normalizedKey, $meta);
                return false;
            }

            $stored = $this->repository()->put($normalizedKey, $payload, max(1, $ttlSeconds));
            if ($stored) {
                $this->incrementMetricCounter('cache_sets');
            }
            $this->metric('CACHE SET', $normalizedKey, array_merge(['ttl' => $ttlSeconds], $meta));
            return $stored;
        } catch (\Throwable $e) {
            $this->metric('CACHE SET FAILED', $normalizedKey, ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function del(string $key): bool
    {
        $normalizedKey = $this->key($key);

        try {
            $deleted = $this->repository()->forget($normalizedKey);
            if ($deleted) {
                $this->incrementMetricCounter('cache_deletes');
            }
            $this->metric('CACHE DELETE', $normalizedKey);
            return $deleted;
        } catch (\Throwable $e) {
            $this->metric('CACHE DEL FAILED', $normalizedKey, ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function forget(string $key): bool
    {
        return $this->del($key);
    }

    public function delByPattern(string $pattern): int
    {
        if (!$this->isRedisStore()) {
            $this->metric('CACHE PATTERN SKIPPED', $pattern, ['reason' => 'store_not_redis']);
            return 0;
        }

        $prefixedPattern = $this->redisKeyPrefix() . $this->key($pattern);
        $cursor = '0';
        $deleted = 0;

        try {
            do {
                [$cursor, $keys] = $this->redisConnection()->command('SCAN', [$cursor, 'MATCH', $prefixedPattern, 'COUNT', 100]);
                if (!empty($keys)) {
                    foreach ($keys as $rawKey) {
                        $logicalKey = Str::startsWith($rawKey, $this->redisKeyPrefix())
                            ? Str::after($rawKey, $this->redisKeyPrefix())
                            : $rawKey;

                        if ($this->repository()->forget($logicalKey)) {
                            $deleted++;
                        }
                    }
                }
            } while ((string) $cursor !== '0');

            $this->metric('CACHE DEL PATTERN', $pattern, ['deleted' => $deleted]);
            return $deleted;
        } catch (\Throwable $e) {
            $this->metric('CACHE DEL PATTERN FAILED', $pattern, ['error' => $e->getMessage()]);
            return 0;
        }
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $normalizedKey = $this->key($key);
        $ttl = $ttlSeconds > 0 ? $ttlSeconds : ($this->config['ttl']['static'] ?? 3600);

        $cached = $this->get($normalizedKey);
        if ($cached !== null) {
            return $cached;
        }

        if ($this->supportsLocks()) {
            $lockKey = $normalizedKey . ':lock';
            $lockSeconds = (int) ($this->config['locks']['seconds'] ?? 10);
            $waitSeconds = (int) ($this->config['locks']['wait_seconds'] ?? 3);

            try {
                return $this->repository()->lock($lockKey, $lockSeconds)->block($waitSeconds, function () use ($normalizedKey, $ttl, $callback) {
                    $cachedAfterLock = $this->repository()->get($normalizedKey);
                    if ($cachedAfterLock !== null) {
                        $this->incrementMetricCounter('cache_hits');
                        $this->metric('CACHE HIT AFTER LOCK', $normalizedKey);
                        return $this->decodeStoredValue($cachedAfterLock);
                    }

                    $result = $callback();
                    $this->set($normalizedKey, $result, $ttl);
                    return $result;
                });
            } catch (\Throwable $e) {
                $this->metric('CACHE LOCK FAILED', $normalizedKey, ['error' => $e->getMessage()]);
            }
        }

        $result = $callback();
        $this->set($normalizedKey, $result, $ttl);

        return $result;
    }

    public function ttl(string $type = 'static'): int
    {
        return (int) ($this->config['ttl'][$type] ?? 3600);
    }

    public function getVersion(string $namespace): int
    {
        $key = $this->versionKey($namespace);

        try {
            $version = $this->repository()->get($key);
            if ($version === null) {
                $this->repository()->forever($key, 1);
                return 1;
            }

            return max(1, (int) $version);
        } catch (\Throwable $e) {
            $this->metric('CACHE VERSION GET FAILED', $key, ['error' => $e->getMessage()]);
            return 1;
        }
    }

    public function bumpVersion(string $namespace): int
    {
        $key = $this->versionKey($namespace);

        try {
            $current = $this->getVersion($namespace);
            $next = $current + 1;
            $this->repository()->forever($key, $next);
            $this->metric('CACHE VERSION BUMP', $key, ['namespace' => $namespace, 'version' => $next]);
            return $next;
        } catch (\Throwable $e) {
            $this->metric('CACHE VERSION BUMP FAILED', $key, ['error' => $e->getMessage()]);
            return 1;
        }
    }

    public function key(string $name): string
    {
        return $this->config['keys'][$name] ?? $name;
    }

    public function makeKey(string $prefix, array $parts = []): string
    {
        $segments = [$prefix];

        foreach ($parts as $partKey => $value) {
            $segments[] = $partKey;
            $segments[] = $value === null ? 'null' : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        return implode(':', $segments);
    }

    public function versionedKey(string $namespace, array $parts = []): string
    {
        $version = $this->getVersion($namespace);
        $segments = [$namespace, 'v', $version];

        foreach ($parts as $partKey => $value) {
            $segments[] = $partKey;
            $segments[] = $value === null ? 'null' : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }

        return implode(':', $segments);
    }

    public function compressPayload(mixed $data): array
    {
        $json = json_encode($data);
        if ($json === false) {
            return ['encoded' => false, 'compressed' => false, 'payload' => null, 'json_bytes' => 0];
        }

        $jsonBytes = strlen($json);
        $threshold = (int) ($this->config['compression']['threshold_bytes'] ?? 2048);
        if ($jsonBytes <= $threshold) {
            return ['encoded' => true, 'compressed' => false, 'payload' => $json, 'json_bytes' => $jsonBytes];
        }

        $compressed = gzencode($json, (int) ($this->config['compression']['level'] ?? 6));
        if ($compressed === false) {
            return ['encoded' => true, 'compressed' => false, 'payload' => $json, 'json_bytes' => $jsonBytes];
        }

        return [
            'encoded' => true,
            'compressed' => true,
            'payload' => base64_encode($compressed),
            'json_bytes' => $jsonBytes,
        ];
    }

    public function decompressPayload(array $payload): mixed
    {
        if (!($payload['compressed'] ?? false)) {
            return json_decode((string) ($payload['payload'] ?? ''), true);
        }

        $decoded = base64_decode((string) ($payload['payload'] ?? ''), true);
        if ($decoded === false) {
            return null;
        }

        $json = gzdecode($decoded);
        if ($json === false) {
            return null;
        }

        return json_decode($json, true);
    }

    public function applyHttpCacheHeaders(Request $request, JsonResponse $response, int $ttlSeconds, string $visibility = 'public'): JsonResponse
    {
        $etag = '"' . sha1((string) $response->getContent()) . '"';
        $cacheControl = sprintf('%s, max-age=%d', $visibility, max(1, $ttlSeconds));

        if (trim((string) $request->headers->get('If-None-Match')) === $etag) {
            return response()->json(null, 304, [
                'Cache-Control' => $cacheControl,
                'ETag' => $etag,
            ]);
        }

        $response->headers->set('Cache-Control', $cacheControl);
        $response->headers->set('ETag', $etag);

        return $response;
    }

    public function flushStatic(): void
    {
        foreach (($this->config['static_namespaces'] ?? []) as $namespace) {
            $this->bumpVersion($namespace);
        }
    }

    private function repository(): Repository
    {
        return Cache::store($this->config['store'] ?? 'redis');
    }

    private function supportsLocks(): bool
    {
        return method_exists($this->repository()->getStore(), 'lock');
    }

    private function isRedisStore(): bool
    {
        return ($this->config['store'] ?? 'redis') === 'redis';
    }

    private function redisConnection()
    {
        return Redis::connection($this->config['redis_connection'] ?? 'cache');
    }

    private function redisKeyPrefix(): string
    {
        return (string) config('database.redis.options.prefix', '') . (string) config('cache.prefix', '');
    }

    private function versionKey(string $namespace): string
    {
        return "cache:version:{$namespace}";
    }

    private function prepareStoredPayload(mixed $value): array
    {
        $encoded = $this->compressPayload($value);
        if (!($encoded['encoded'] ?? false)) {
            return [true, $value, ['serialized' => false]];
        }

        $wrapped = self::ENCODED_PAYLOAD_PREFIX . json_encode([
            'compressed' => (bool) ($encoded['compressed'] ?? false),
            'payload' => $encoded['payload'],
        ]);

        $payloadBytes = strlen($wrapped);
        if ($payloadBytes > ((int) ($this->config['limits']['max_payload_bytes'] ?? self::MAX_CACHE_PAYLOAD_BYTES))) {
            return [false, null, [
                'bytes' => $payloadBytes,
                'json_bytes' => (int) ($encoded['json_bytes'] ?? 0),
                'compressed' => (bool) ($encoded['compressed'] ?? false),
            ]];
        }

        return [true, $wrapped, [
            'bytes' => $payloadBytes,
            'json_bytes' => (int) ($encoded['json_bytes'] ?? 0),
            'compressed' => (bool) ($encoded['compressed'] ?? false),
            'serialized' => true,
        ]];
    }

    private function decodeStoredValue(mixed $value, mixed $default = null): mixed
    {
        if (!is_string($value) || !Str::startsWith($value, self::ENCODED_PAYLOAD_PREFIX)) {
            return $value;
        }

        $wrapper = json_decode(Str::after($value, self::ENCODED_PAYLOAD_PREFIX), true);
        if (!is_array($wrapper)) {
            return $default;
        }

        $decoded = $this->decompressPayload($wrapper);

        return $decoded ?? $default;
    }

    private function incrementMetricCounter(string $metric): void
    {
        try {
            $this->repository()->increment("cache:metrics:{$metric}");
        } catch (\Throwable) {
            // best effort only
        }
    }

    private function metric(string $message, string $key, array $context = []): void
    {
        Log::info($message, array_merge(['key' => $key], $context));
    }
}
