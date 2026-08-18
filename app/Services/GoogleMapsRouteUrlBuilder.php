<?php

namespace App\Services;

use App\Models\RoutePlan;
use App\Models\Stop;

final class GoogleMapsRouteUrlBuilder
{
    private const MAX_POINTS_PER_LINK = 11;

    /** @return array<int, string> */
    public function build(RoutePlan $plan): array
    {
        $origin = $plan->stops->firstWhere('type', 'start');
        $visits = $plan->stops->where('type', 'visit')->sortBy('optimized_order')->values();
        $points = collect([$origin])->concat($visits);

        if ($plan->return_to_start) {
            $points->push($origin);
        }

        $links = [];
        $offset = 0;

        while ($offset < $points->count() - 1) {
            $segment = $points->slice($offset, self::MAX_POINTS_PER_LINK)->values();
            $links[] = $this->buildSegment($segment->all(), $plan->avoid_tolls);
            $offset += $segment->count() - 1;
        }

        return $links;
    }

    /** @param array<int, Stop> $points */
    private function buildSegment(array $points, bool $avoidTolls): string
    {
        $parameters = [
            'api' => 1,
            'origin' => $this->coordinates($points[0]),
            'destination' => $this->coordinates($points[array_key_last($points)]),
            'travelmode' => 'driving',
            'dir_action' => 'navigate',
        ];
        $waypoints = array_slice($points, 1, -1);

        if ($waypoints !== []) {
            $parameters['waypoints'] = implode('|', array_map($this->coordinates(...), $waypoints));
        }

        if ($avoidTolls) {
            $parameters['avoid'] = 'tolls';
        }

        return 'https://www.google.com/maps/dir/?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    private function coordinates(Stop $stop): string
    {
        return "{$stop->latitude},{$stop->longitude}";
    }
}
