<?php

namespace App\Actions;

use App\Models\RoutePlan;
use App\Models\Stop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteRouteStop
{
    public function __construct(private ReorderRoutePlan $reorderRoutePlan) {}

    public function execute(RoutePlan $plan, Stop $stop): RoutePlan
    {
        if ($stop->route_plan_id !== $plan->id || $stop->type !== 'visit') {
            throw ValidationException::withMessages(['stop' => 'Η στάση δεν ανήκει σε αυτή τη διαδρομή.']);
        }

        if ($plan->stops()->where('type', 'visit')->count() <= 2) {
            throw ValidationException::withMessages(['stop' => 'Η διαδρομή πρέπει να έχει τουλάχιστον δύο στάσεις.']);
        }

        return DB::transaction(function () use ($plan, $stop) {
            $stop->delete();
            $stopIds = $plan->stops()->where('type', 'visit')->orderBy('optimized_order')->pluck('id')->all();

            return $this->reorderRoutePlan->execute($plan, $stopIds);
        });
    }
}
