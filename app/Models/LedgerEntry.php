<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    //
    protected $fillable = [
        'wallet_id',
        'account_type',
        'debit',
        'credit',
        'transaction_id',
    ];

    protected $casts = [
        'debit' => 'decimal:8',
        'credit' => 'decimal:8',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
