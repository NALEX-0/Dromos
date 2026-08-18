<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeocodedAddressCache extends Model
{
    protected $table = 'geocoded_address_cache';

    protected $fillable = [
        'normalized_address',
        'requested_address',
        'formatted_address',
        'latitude',
        'longitude',
        'place_id',
        'hit_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'hit_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }
}
