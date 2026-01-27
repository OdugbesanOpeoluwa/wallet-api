<?php

namespace App\Services;

use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;

class WalletCache
{
    protected static int $ttl = 60; // seconds

    public static function get(string $id): ?Wallet
    {
        return Cache::remember("wallet:{$id}", self::$ttl, function () use ($id) {
            return Wallet::find($id);
        });
    }

    public static function getBalance(string $id): ?float
    {
        $wallet = self::get($id);
        return $wallet?->balance;
    }

    public static function forget(string $id): void
    {
        Cache::forget("wallet:{$id}");
    }

    public static function refresh(Wallet $wallet): Wallet
    {
        self::forget($wallet->id);
        Cache::put("wallet:{$wallet->id}", $wallet, self::$ttl);
        return $wallet;
    }
}
