<?php

namespace App\Actions;

use App\Contracts\RouteOptimizer;
use App\Data\GeocodedAddress;
use App\Models\RoutePlan;
use App\Services\CalculateOrderedRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReorderRoutePlan
{
    public function __construct(private RouteOptimizer $optimizer, private CalculateOrderedRoute $orderedRouteCalculator) {}

    public function execute(RoutePlan $plan, array $stopIds): RoutePlan
    {
        $stopIds = collect($stopIds)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return DB::transaction(function () use ($plan, $stopIds) {
            $start = $plan->stops()->where('type', 'start')->firstOrFail();
            $visits = $plan->stops()->where('type', 'visit')->get()->keyBy('id');
            if (collect($stopIds)->sort()->values()->all() !== $visits->keys()->sort()->values()->all()) {
                throw ValidationException::withMessages(['stop_ids' => 'Η σειρά των στάσεων δεν αντιστοιχεί σε αυτή τη διαδρομή.']);
            }

            $point = fn ($stop) => new GeocodedAddress($stop->formatted_address, $stop->latitude, $stop->longitude, $stop->place_id);
            $ordered = collect($stopIds)->map(fn ($id) => $visits[$id]);
            $waypoints = $ordered->map($point)->all();
            $result = $plan->route_mode === 'ordered'
                ? $this->orderedRouteCalculator->execute($point($start), $waypoints, $plan->return_to_start, $plan->avoid_tolls)
                : $this->optimizer->routeInOrder($point($start), $waypoints, returnToStart: $plan->return_to_start, avoidTolls: $plan->avoid_tolls);

            foreach ($ordered as $position => $stop) {
                $stop->update(['optimized_order' => $position + 1, 'leg_distance_meters' => $result->legs[$position]['distance'] ?? null, 'leg_duration_seconds' => $result->legs[$position]['duration'] ?? null]);
            }
            $plan->update(['total_distance_meters' => $result->distanceMeters, 'total_duration_seconds' => $result->durationSeconds, 'encoded_polyline' => $result->encodedPolyline, 'encoded_polylines' => $result->raw['encoded_polylines'] ?? array_filter([$result->encodedPolyline]), 'provider_payload' => $result->raw]);

            return $plan->fresh('stops');
        });
    }
}
