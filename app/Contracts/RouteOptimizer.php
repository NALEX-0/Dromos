<?php

namespace App\Contracts;

use App\Data\GeocodedAddress;
use App\Data\RouteResult;

interface RouteOptimizer
{
    /** @param array<int, GeocodedAddress> $waypoints */
    public function optimize(GeocodedAddress $origin, array $waypoints, bool $returnToStart = false, bool $avoidTolls = false): RouteResult;

    /** @param array<int, GeocodedAddress> $waypoints */
    public function routeInOrder(GeocodedAddress $origin, array $waypoints, bool $returnToStart = false, bool $avoidTolls = false): RouteResult;

    /** @param array<int, GeocodedAddress> $waypoints */
    public function routeInOrderEconomy(GeocodedAddress $origin, array $waypoints, bool $returnToStart = false, bool $avoidTolls = false): RouteResult;
}
