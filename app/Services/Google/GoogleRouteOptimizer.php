<?php

namespace App\Services\Google;

use App\Contracts\RouteOptimizer;
use App\Data\GeocodedAddress;
use App\Data\RouteResult;
use Illuminate\Http\Client\Factory;

final readonly class GoogleRouteOptimizer implements RouteOptimizer
{
    public function __construct(private Factory $http) {}

    public function optimize(GeocodedAddress $origin, array $waypoints, bool $returnToStart = false, bool $avoidTolls = false): RouteResult
    {
        return $this->compute($origin, $waypoints, $returnToStart, true, $avoidTolls, true);
    }

    public function routeInOrder(GeocodedAddress $origin, array $waypoints, bool $returnToStart = false, bool $avoidTolls = false): RouteResult
    {
        return $this->compute($origin, $waypoints, $returnToStart, false, $avoidTolls, true);
    }

    public function routeInOrderEconomy(GeocodedAddress $origin, array $waypoints, bool $returnToStart = false, bool $avoidTolls = false): RouteResult
    {
        return $this->compute($origin, $waypoints, $returnToStart, false, $avoidTolls, false);
    }

    private function compute(GeocodedAddress $origin, array $waypoints, bool $returnToStart, bool $optimize, bool $avoidTolls, bool $trafficAware): RouteResult
    {
        $location = fn (GeocodedAddress $point) => ['location' => ['latLng' => ['latitude' => $point->latitude, 'longitude' => $point->longitude]]];
        $fixedDestinationIndex = $returnToStart ? null : array_key_last($waypoints);
        $destination = $returnToStart ? $origin : array_pop($waypoints);
        $body = ['origin' => $location($origin), 'destination' => $location($destination), 'intermediates' => array_map($location, $waypoints), 'travelMode' => 'DRIVE', 'routeModifiers' => ['avoidTolls' => $avoidTolls], 'optimizeWaypointOrder' => $optimize && count($waypoints) > 1, 'languageCode' => 'el-GR', 'units' => 'METRIC'];

        if ($trafficAware) {
            $body['routingPreference'] = 'TRAFFIC_AWARE';
        }
        $route = $this->http->withHeaders(['X-Goog-Api-Key' => config('services.google.server_key'), 'X-Goog-FieldMask' => 'routes.distanceMeters,routes.duration,routes.polyline.encodedPolyline,routes.optimizedIntermediateWaypointIndex,routes.legs.distanceMeters,routes.legs.duration'])->post('https://routes.googleapis.com/directions/v2:computeRoutes', $body)->throw()->json('routes.0');
        $seconds = fn (string $duration) => (int) round((float) rtrim($duration, 's'));
        $legs = array_map(fn ($leg) => ['distance' => $leg['distanceMeters'], 'duration' => $seconds($leg['duration'])], $route['legs'] ?? []);
        $order = $optimize ? ($route['optimizedIntermediateWaypointIndex'] ?? array_keys($waypoints)) : array_keys($waypoints);
        if ($fixedDestinationIndex !== null) {
            $order[] = $fixedDestinationIndex;
        }

        return new RouteResult($order, $route['distanceMeters'], $seconds($route['duration']), $route['polyline']['encodedPolyline'] ?? null, $legs, $route);
    }
}
