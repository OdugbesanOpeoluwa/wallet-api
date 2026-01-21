<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class WebhookLog extends Model
{
    //
    use HasUuids;
    
    protected $fillable = [
        'provider',
        'payload',
        'signature',
        'event_id',
        'processed',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
    ];

}
