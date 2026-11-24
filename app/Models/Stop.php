<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Stop extends Model
{
    protected $fillable = ['route_plan_id', 'type', 'input_order', 'optimized_order', 'address', 'phone', 'postal_code', 'city', 'region', 'formatted_address', 'latitude', 'longitude', 'place_id', 'geocoding_status', 'leg_distance_meters', 'leg_duration_seconds'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }

    public function routePlan(): BelongsTo
    {
        return $this->belongsTo(RoutePlan::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(StopNotification::class);
    }

    public function latestNotification(): HasOne
    {
        return $this->hasOne(StopNotification::class)->latestOfMany();
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->postal_code, $this->city, $this->region, 'Greece'])->filter()->implode(', ');
    }
}
