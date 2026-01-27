<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class IdempotencyService
{
    protected int $ttl = 259200; // 72 hours

    public function check(string $key, array $params, ?string $userId = null): ?array
    {
        $cacheKey = $userId ? "idem:{$userId}:{$key}" : "idem:{$key}";
        $stored = Cache::get($cacheKey);

        if (!$stored) return null;

        // verify params match
        $hash = $this->hash($params);
        if ($stored['hash'] !== $hash) {
            throw new \Exception('Idempotency key used with different params');
        }

        return $stored['response'];
    }

    public function store(string $key, array $params, array $response, ?string $userId = null): void
    {
        $cacheKey = $userId ? "idem:{$userId}:{$key}" : "idem:{$key}";

        Cache::put($cacheKey, [
            'hash' => $this->hash($params),
            'response' => $response,
        ], $this->ttl);
    }

    protected function hash(array $params): string
    {
        unset($params['timestamp'], $params['_token']);
        ksort($params);
        return hash('sha256', json_encode($params));
    }
}
