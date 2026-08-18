<?php

namespace App\Actions;

use App\Contracts\Geocoder;
use App\Models\RoutePlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class AddRouteStop
{
    public function __construct(private Geocoder $geocoder, private ReorderRoutePlan $reorderRoutePlan) {}

    public function execute(RoutePlan $plan, string $address): RoutePlan
    {
        $maximumStops = $plan->route_mode === 'ordered' ? 100 : 25;

        if ($plan->stops()->where('type', 'visit')->count() >= $maximumStops) {
            throw ValidationException::withMessages(['stop' => "Μπορείτε να προσθέσετε έως {$maximumStops} στάσεις."]);
        }

        return DB::transaction(function () use ($plan, $address) {
            $geocoded = $this->geocoder->geocode("{$address}, Greece");
            $nextOrder = $plan->stops()->where('type', 'visit')->max('optimized_order') + 1;
            $nextInputOrder = $plan->stops()->max('input_order') + 1;

            $plan->stops()->create([
                'type' => 'visit',
                'input_order' => $nextInputOrder,
                'optimized_order' => $nextOrder,
                'address' => $address,
                'city' => '',
                'formatted_address' => $geocoded->formattedAddress,
                'latitude' => $geocoded->latitude,
                'longitude' => $geocoded->longitude,
                'place_id' => $geocoded->placeId,
                'geocoding_status' => 'verified',
            ]);

            $stopIds = $plan->stops()->where('type', 'visit')->orderBy('optimized_order')->pluck('id')->all();

            return $this->reorderRoutePlan->execute($plan, $stopIds);
        });
    }
}
