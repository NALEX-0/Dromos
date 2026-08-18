<?php

namespace App\Data;

final readonly class RouteResult
{
    /** @param array<int, int> $optimizedWaypointOrder @param array<int, array{distance: int, duration: int}> $legs */
    public function __construct(public array $optimizedWaypointOrder, public int $distanceMeters, public int $durationSeconds, public ?string $encodedPolyline, public array $legs = [], public array $raw = []) {}
}
