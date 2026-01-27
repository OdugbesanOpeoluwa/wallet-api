<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    use HasUuids;

    protected $fillable = [
        'journal_id',
        'account_id',
        'amount',
        'type',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
    ];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
