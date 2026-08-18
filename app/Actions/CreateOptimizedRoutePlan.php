<?php

namespace App\Actions;

use App\Contracts\Geocoder;
use App\Contracts\RouteOptimizer;
use App\Models\RoutePlan;
use Illuminate\Support\Facades\DB;

final readonly class CreateOptimizedRoutePlan
{
    public function __construct(private Geocoder $geocoder, private RouteOptimizer $optimizer) {}

    public function execute(array $data): RoutePlan
    {
        return DB::transaction(function () use ($data) {
            $plan = RoutePlan::create(['name' => $data['name'] ?? 'Athens route', 'status' => 'processing', 'provider' => config('services.routing_provider'), 'avoid_tolls' => (bool) ($data['avoid_tolls'] ?? false), 'return_to_start' => (bool) ($data['return_to_start'] ?? false)]);
            $plan->update(['user_id' => auth()->id()]);
            $rows = array_merge([array_merge($data['start'], ['type' => 'start'])], array_map(fn ($stop) => array_merge($stop, ['type' => 'visit']), $data['stops']));
            $geocoded = [];
            foreach ($rows as $index => $row) {
                $query = collect([$row['address'], $row['postal_code'] ?? null, $row['city'] ?? null, $row['region'] ?? null, 'Greece'])->filter()->implode(', ');
                $geo = $this->geocoder->geocode($query);
                $geocoded[] = $geo;
                $plan->stops()->create(array_merge(['city' => ''], $row, ['input_order' => $index, 'optimized_order' => $index === 0 ? 0 : null, 'formatted_address' => $geo->formattedAddress, 'latitude' => $geo->latitude, 'longitude' => $geo->longitude, 'place_id' => $geo->placeId, 'geocoding_status' => 'verified']));
            }
            $result = $this->optimizer->optimize(array_shift($geocoded), $geocoded, (bool) ($data['return_to_start'] ?? false), (bool) ($data['avoid_tolls'] ?? false));
            $visits = $plan->stops()->where('type', 'visit')->orderBy('input_order')->get();
            foreach ($result->optimizedWaypointOrder as $position => $inputIndex) {
                $visits[$inputIndex]?->update(['optimized_order' => $position + 1, 'leg_distance_meters' => $result->legs[$position]['distance'] ?? null, 'leg_duration_seconds' => $result->legs[$position]['duration'] ?? null]);
            }
            $plan->update(['status' => 'ready', 'total_distance_meters' => $result->distanceMeters, 'total_duration_seconds' => $result->durationSeconds, 'encoded_polyline' => $result->encodedPolyline, 'encoded_polylines' => array_filter([$result->encodedPolyline]), 'provider_payload' => $result->raw]);

            return $plan->fresh('stops');
        });
    }
}
