<?php

namespace App\Contracts;

use App\Data\GeocodedAddress;

interface Geocoder
{
    public function geocode(string $address): GeocodedAddress;
}
