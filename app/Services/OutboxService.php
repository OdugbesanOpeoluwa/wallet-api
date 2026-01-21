<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OutboxService
{
    public static function store(string $eventType, string $aggregateId, array $payload): void
    {
        DB::table('outbox')->insert([
            'event_type' => $eventType,
            'aggregate_id' => $aggregateId,
            'payload' => json_encode($payload),
            'processed' => false,
            'created_at' => now(),
        ]);
    }
}