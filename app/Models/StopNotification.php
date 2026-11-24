<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StopNotification extends Model
{
    protected $fillable = [
        'stop_id',
        'user_id',
        'provider',
        'provider_message_id',
        'phone',
        'message',
        'status',
        'failure_reason',
        'sent_at',
        'delivered_at',
        'provider_response',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'provider_response' => 'array',
        ];
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
