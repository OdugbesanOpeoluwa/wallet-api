<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    use HasUuids;

    protected $fillable = [
        'reference',
        'type',
        'amount',
        'currency',
        'status',
        'metadata',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public function isBalanced(): bool
    {
        $sum = $this->entries()->sum('amount');
        return abs($sum) < 0.0001;
    }
}
