<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColoredRoute extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'color',
        'addresses',
        'encoded_polyline',
        'path',
        'distance_meters',
        'duration_seconds',
        'is_circular',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'addresses' => 'array',
            'path' => 'array',
            'is_circular' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
