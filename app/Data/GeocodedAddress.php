<?php

namespace App\Data;

final readonly class GeocodedAddress
{
    public function __construct(public string $formattedAddress, public float $latitude, public float $longitude, public ?string $placeId = null) {}
}
