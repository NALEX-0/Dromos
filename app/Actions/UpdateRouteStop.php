<?php

namespace App\Actions;

use App\Contracts\Geocoder;
use App\Models\RoutePlan;
use App\Models\Stop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateRouteStop
{
    public function __construct(private Geocoder $geocoder, private ReorderRoutePlan $reorderRoutePlan) {}

    public function execute(RoutePlan $plan, Stop $stop, string $address): RoutePlan
    {
        $this->ensureStopBelongsToPlan($plan, $stop);

        return DB::transaction(function () use ($plan, $stop, $address) {
            $geocoded = $this->geocoder->geocode("{$address}, Greece");
            $stop->update([
                'address' => $address,
                'formatted_address' => $geocoded->formattedAddress,
                'latitude' => $geocoded->latitude,
                'longitude' => $geocoded->longitude,
                'place_id' => $geocoded->placeId,
                'geocoding_status' => 'verified',
            ]);

            return $this->reorderRoutePlan->execute($plan, $this->orderedStopIds($plan));
        });
    }

    private function ensureStopBelongsToPlan(RoutePlan $plan, Stop $stop): void
    {
        if ($stop->route_plan_id !== $plan->id || $stop->type !== 'visit') {
            throw ValidationException::withMessages(['stop' => 'Η στάση δεν ανήκει σε αυτή τη διαδρομή.']);
        }
    }

    /** @return array<int, int> */
    private function orderedStopIds(RoutePlan $plan): array
    {
        return $plan->stops()->where('type', 'visit')->orderBy('optimized_order')->pluck('id')->all();
    }
}
