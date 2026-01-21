<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Transaction extends Model
{
    //
    use HasUuids;

    protected $fillable = [
        'reference',
        'type',
        'status',
        'amount',
        'currency',
        'sender_wallet_id',
        'recipient_wallet_id',
        'metadata',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function senderWallet()
    {
        return $this->belongsTo(Wallet::class, 'sender_wallet_id');
    }

    public function recipientWallet()
    {
        return $this->belongsTo(Wallet::class, 'recipient_wallet_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
