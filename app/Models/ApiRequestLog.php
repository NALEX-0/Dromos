<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'service',
        'operation',
        'status',
        'http_status',
        'duration_ms',
        'cache_hit',
        'error_type',
        'error_message',
        'metadata',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'cache_hit' => 'boolean',
            'metadata' => 'array',
            'requested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
