<?php

namespace App\Services\Google;

use App\Contracts\Geocoder;
use App\Data\GeocodedAddress;
use Illuminate\Http\Client\Factory;
use RuntimeException;

final readonly class GoogleGeocoder implements Geocoder
{
    public function __construct(private Factory $http) {}

    public function geocode(string $address): GeocodedAddress
    {
        $response = $this->http->get('https://maps.googleapis.com/maps/api/geocode/json', ['address' => $address, 'region' => 'gr', 'language' => 'el', 'key' => config('services.google.server_key')])->throw()->json();
        $result = $response['results'][0] ?? null;
        if (! $result) {
            throw new RuntimeException("Google could not locate: {$address}");
        }

        return new GeocodedAddress($result['formatted_address'], $result['geometry']['location']['lat'], $result['geometry']['location']['lng'], $result['place_id'] ?? null);
    }
}
