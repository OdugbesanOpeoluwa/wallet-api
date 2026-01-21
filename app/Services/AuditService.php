<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditService
{
    public static function log(string $action, string $resource, ?string $resourceId, array $data = []): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'ip' => request()->ip(),
            'new_values' => json_encode($data),
            'created_at' => now(),
        ]);
    }
}