<?php

namespace App\Policies;

use App\Models\RoutePlan;
use App\Models\User;

class RoutePlanPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, RoutePlan $routePlan): bool
    {
        return $routePlan->user_id === $user->id;
    }

    public function update(User $user, RoutePlan $routePlan): bool
    {
        return $routePlan->user_id === $user->id;
    }

    public function delete(User $user, RoutePlan $routePlan): bool
    {
        return $routePlan->user_id === $user->id;
    }
}
