<?php

namespace App\Services;

use App\Contracts\Geocoder;
use App\Data\GeocodedAddress;
use App\Models\GeocodedAddressCache;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final readonly class CachedGeocoder implements Geocoder
{
    public function __construct(private Geocoder $geocoder) {}

    public function geocode(string $address): GeocodedAddress
    {
        $normalizedAddress = $this->normalize($address);
        $cached = GeocodedAddressCache::query()
            ->where('normalized_address', $normalizedAddress)
            ->first();

        if ($cached) {
            $cached->increment('hit_count');
            $cached->update(['last_used_at' => now()]);

            return $this->toData($cached);
        }

        $geocoded = $this->geocoder->geocode($address);

        try {
            $cached = GeocodedAddressCache::query()->create([
                'normalized_address' => $normalizedAddress,
                'requested_address' => $address,
                'formatted_address' => $geocoded->formattedAddress,
                'latitude' => $geocoded->latitude,
                'longitude' => $geocoded->longitude,
                'place_id' => $geocoded->placeId,
                'last_used_at' => now(),
            ]);
        } catch (QueryException) {
            $cached = GeocodedAddressCache::query()
                ->where('normalized_address', $normalizedAddress)
                ->firstOrFail();
        }

        return $this->toData($cached);
    }

    private function normalize(string $address): string
    {
        $address = Str::lower(trim($address));
        $address = preg_replace('/\s*,\s*/u', ',', $address);

        return preg_replace('/\s+/u', ' ', $address);
    }

    private function toData(GeocodedAddressCache $cached): GeocodedAddress
    {
        return new GeocodedAddress(
            $cached->formatted_address,
            $cached->latitude,
            $cached->longitude,
            $cached->place_id,
        );
    }
}
