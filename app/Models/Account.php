<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'wallet_id',
        'currency',
        'balance',
        'name',
    ];

    protected $casts = [
        'balance' => 'decimal:8',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public static function getSystemAccount(string $type, string $currency)
    {
        return self::where('type', $type)
            ->where('currency', $currency)
            ->whereNull('wallet_id')
            ->first();
    }

    public static function forWallet(Wallet $wallet)
    {
        return self::firstOrCreate(
            ['wallet_id' => $wallet->id, 'currency' => $wallet->currency],
            ['type' => 'USER_WALLET', 'balance' => $wallet->balance, 'name' => null]
        );
    }
}
