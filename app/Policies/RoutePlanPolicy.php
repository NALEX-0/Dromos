<?php

namespace App\Policies;

use App\Models\RoutePlan;
use App\Models\User;

class RoutePlanPolicy
{
    public function view(User $user, RoutePlan $routePlan): bool
    {
        return $routePlan->user_id === $user->id;
    }

    public function update(User $user, RoutePlan $routePlan): bool
    {
        return $routePlan->user_id === $user->id;
    }
}
