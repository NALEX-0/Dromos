<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutePlan extends Model
{
    protected $fillable = ['user_id', 'name', 'status', 'route_mode', 'provider', 'avoid_tolls', 'return_to_start', 'total_distance_meters', 'total_duration_seconds', 'encoded_polyline', 'encoded_polylines', 'provider_payload'];

    protected function casts(): array
    {
        return ['avoid_tolls' => 'boolean', 'return_to_start' => 'boolean', 'encoded_polylines' => 'array', 'provider_payload' => 'array'];
    }

    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderedStops(): HasMany
    {
        return $this->stops()->orderBy('optimized_order');
    }
}
