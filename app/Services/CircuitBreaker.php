<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    protected string $service;
    protected int $threshold = 5;
    protected int $timeout = 60;

    public function __construct(string $service)
    {
        $this->service = $service;
    }

    public function isOpen(): bool
    {
        return Cache::get("circuit:{$this->service}:open", false);
    }

    public function recordFailure(): void
    {
        $key = "circuit:{$this->service}:failures";
        $failures = Cache::increment($key);
        Cache::put($key, $failures, 120);

        if ($failures >= $this->threshold) {
            Cache::put("circuit:{$this->service}:open", true, $this->timeout);
        }
    }

    public function recordSuccess(): void
    {
        Cache::forget("circuit:{$this->service}:failures");
        Cache::forget("circuit:{$this->service}:open");
    }

    public function call(callable $fn)
    {
        if ($this->isOpen()) {
            throw new \Exception('Service unavailable');
        }

        try {
            $result = $fn();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }
}