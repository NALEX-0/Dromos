<?php

namespace App\Services\Demo;

use App\Contracts\RouteOptimizer;
use App\Data\GeocodedAddress;
use App\Data\RouteResult;

final class DemoRouteOptimizer implements RouteOptimizer
{
    public function optimize(GeocodedAddress $origin, array $waypoints, bool $returnToStart = false, bool $avoidTolls = false): RouteResult
    {
        $remaining = array_keys($waypoints);
        $order = [];
        $legs = [];
        $current = $origin;
        $total = 0;
        while ($remaining) {
            usort($remaining, fn ($a, $b) => $this->distance($current, $waypoints[$a]) <=> $this->distance($current, $waypoints[$b]));
            $next = array_shift($remaining);
            $meters = (int) round($this->distance($current, $waypoints[$next]));
            $order[] = $next;
            $legs[] = ['distance' => $meters, 'duration' => (int) round($meters / 7.2)];
            $total += $meters;
            $current = $waypoints[$next];
        }
        if ($returnToStart && $order) {
            $meters = (int) round($this->distance($current, $origin));
            $total += $meters;
            $legs[] = ['distance' => $meters, 'duration' => (int) round($meters / 7.2)];
        }

        return new RouteResult($order, $total, array_sum(array_column($legs, 'duration')), null, $legs, ['demo' => true, 'avoid_tolls' => $avoidTolls]);
    }

    public function routeInOrder(GeocodedAddress $origin, array $waypoints, bool $returnToStart = false, bool $avoidTolls = false): RouteResult
    {
        $legs = [];
        $current = $origin;
        foreach ($waypoints as $waypoint) {
            $meters = (int) round($this->distance($current, $waypoint));
            $legs[] = ['distance' => $meters, 'duration' => (int) round($meters / 7.2)];
            $current = $waypoint;
        }
        if ($returnToStart && $waypoints) {
            $meters = (int) round($this->distance($current, $origin));
            $legs[] = ['distance' => $meters, 'duration' => (int) round($meters / 7.2)];
        }

        return new RouteResult(array_keys($waypoints), array_sum(array_column($legs, 'distance')), array_sum(array_column($legs, 'duration')), null, $legs, ['demo' => true, 'manually_ordered' => true, 'avoid_tolls' => $avoidTolls]);
    }

    public function routeInOrderEconomy(GeocodedAddress $origin, array $waypoints, bool $returnToStart = false, bool $avoidTolls = false): RouteResult
    {
        $result = $this->routeInOrder($origin, $waypoints, $returnToStart, $avoidTolls);

        return new RouteResult($result->optimizedWaypointOrder, $result->distanceMeters, $result->durationSeconds, $result->encodedPolyline, $result->legs, [...$result->raw, 'economy' => true]);
    }

    private function distance(GeocodedAddress $a, GeocodedAddress $b): float
    {
        $lat = deg2rad($b->latitude - $a->latitude);
        $lng = deg2rad($b->longitude - $a->longitude);
        $x = sin($lat / 2) ** 2 + cos(deg2rad($a->latitude)) * cos(deg2rad($b->latitude)) * sin($lng / 2) ** 2;

        return 6371000 * 2 * atan2(sqrt($x), sqrt(1 - $x));
    }
}
