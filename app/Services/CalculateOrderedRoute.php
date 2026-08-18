<?php

namespace App\Services;

use App\Contracts\RouteOptimizer;
use App\Data\GeocodedAddress;
use App\Data\RouteResult;

final readonly class CalculateOrderedRoute
{
    private const MAX_POINTS_PER_REQUEST = 12;

    public function __construct(private RouteOptimizer $optimizer) {}

    /** @param array<int, GeocodedAddress> $waypoints */
    public function execute(GeocodedAddress $origin, array $waypoints, bool $returnToStart, bool $avoidTolls): RouteResult
    {
        $points = [$origin, ...$waypoints];

        if ($returnToStart) {
            $points[] = $origin;
        }

        $distance = 0;
        $duration = 0;
        $legs = [];
        $polylines = [];
        $segments = [];
        $offset = 0;

        while ($offset < count($points) - 1) {
            $segmentPoints = array_slice($points, $offset, self::MAX_POINTS_PER_REQUEST);
            $segment = $this->optimizer->routeInOrderEconomy(array_shift($segmentPoints), $segmentPoints, avoidTolls: $avoidTolls);
            $distance += $segment->distanceMeters;
            $duration += $segment->durationSeconds;
            $legs = [...$legs, ...$segment->legs];
            $segments[] = $segment->raw;

            if ($segment->encodedPolyline) {
                $polylines[] = $segment->encodedPolyline;
            }

            $offset += count($segmentPoints);
        }

        return new RouteResult(
            array_keys($waypoints),
            $distance,
            $duration,
            $polylines[0] ?? null,
            $legs,
            ['segments' => $segments, 'encoded_polylines' => $polylines, 'economy_ordered' => true, 'avoid_tolls' => $avoidTolls],
        );
    }
}
