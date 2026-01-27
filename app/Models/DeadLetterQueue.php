<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DeadLetterQueue extends Model
{
    use HasUuids;

    protected $table = 'dead_letter_queue';

    protected $fillable = [
        'type',
        'payload',
        'error',
        'attempts',
        'resolved',
        'resolved_at',
        'resolved_by',
        'notes',
    ];

    protected $casts = [
        'payload' => 'array',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    public function resolve(string $by, ?string $notes = null): void
    {
        $this->update([
            'resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $by,
            'notes' => $notes,
        ]);
    }
}
