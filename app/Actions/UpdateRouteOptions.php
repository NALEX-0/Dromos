<?php

namespace App\Actions;

use App\Contracts\RouteOptimizer;
use App\Data\GeocodedAddress;
use App\Models\RoutePlan;
use App\Services\CalculateOrderedRoute;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRouteOptions
{
    public function __construct(
        private RouteOptimizer $optimizer,
        private CalculateOrderedRoute $orderedRouteCalculator,
    ) {}

    public function execute(RoutePlan $plan, bool $avoidTolls): RoutePlan
    {
        return DB::transaction(function () use ($plan, $avoidTolls) {
            $start = $plan->stops()->where('type', 'start')->firstOrFail();
            $visits = $plan->stops()
                ->where('type', 'visit')
                ->orderBy('optimized_order')
                ->get()
                ->values();

            $point = fn ($stop) => new GeocodedAddress(
                $stop->formatted_address,
                $stop->latitude,
                $stop->longitude,
                $stop->place_id,
            );

            $waypoints = $visits->map($point)->all();
            $result = $plan->route_mode === 'ordered'
                ? $this->orderedRouteCalculator->execute($point($start), $waypoints, $plan->return_to_start, $avoidTolls)
                : $this->optimizer->optimize($point($start), $waypoints, $plan->return_to_start, $avoidTolls);

            $orderedStops = $plan->route_mode === 'ordered'
                ? $visits
                : collect($result->optimizedWaypointOrder)
                    ->map(fn ($inputIndex) => $visits[$inputIndex])
                    ->values();

            foreach ($orderedStops as $position => $stop) {
                $stop->update([
                    'optimized_order' => $position + 1,
                    'leg_distance_meters' => $result->legs[$position]['distance'] ?? null,
                    'leg_duration_seconds' => $result->legs[$position]['duration'] ?? null,
                ]);
            }

            $plan->update([
                'avoid_tolls' => $avoidTolls,
                'total_distance_meters' => $result->distanceMeters,
                'total_duration_seconds' => $result->durationSeconds,
                'encoded_polyline' => $result->encodedPolyline,
                'encoded_polylines' => $result->raw['encoded_polylines'] ?? array_filter([$result->encodedPolyline]),
                'provider_payload' => $result->raw,
            ]);

            return $plan->fresh('stops');
        });
    }
}
