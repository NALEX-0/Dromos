<?php

namespace App\Services\Demo;

use App\Contracts\Geocoder;
use App\Data\GeocodedAddress;

final class DemoGeocoder implements Geocoder
{
    public function geocode(string $address): GeocodedAddress
    {
        $hash = abs(crc32(mb_strtolower($address)));
        $lat = 37.9838 + ((($hash % 1000) / 1000) - .5) * .18;
        $lng = 23.7275 + (((intdiv($hash, 1000) % 1000) / 1000) - .5) * .24;

        return new GeocodedAddress($address, $lat, $lng, 'demo-'.$hash);
    }
}
