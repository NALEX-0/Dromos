<?php

namespace App\Http\Controllers;

use App\Actions\AddRouteStop;
use App\Actions\CreateOptimizedRoutePlan;
use App\Actions\CreateOrderedRoutePlan;
use App\Actions\DeleteRouteStop;
use App\Actions\ReorderRoutePlan;
use App\Actions\UpdateRouteStop;
use App\Http\Requests\StoreOrderedRoutePlanRequest;
use App\Http\Requests\StoreRoutePlanRequest;
use App\Models\RoutePlan;
use App\Models\Stop;
use App\Services\GoogleMapsRouteUrlBuilder;
use Illuminate\Http\Request;
use Throwable;

class RoutePlanController extends Controller
{
    public function create()
    {
        return view('route-plans.workspace', ['plan' => null, 'browserKey' => config('services.google.browser_key'), 'plannerMode' => 'optimized', 'maximumStops' => 25]);
    }

    public function createOrdered()
    {
        return view('route-plans.workspace', ['plan' => null, 'browserKey' => config('services.google.browser_key'), 'plannerMode' => 'ordered', 'maximumStops' => 100]);
    }

    public function store(StoreRoutePlanRequest $request, CreateOptimizedRoutePlan $action)
    {
        try {
            $plan = $action->execute($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['route' => $exception->getMessage()]);
        }

        return redirect()->route('route-plans.show', $plan);
    }

    public function storeOrdered(StoreOrderedRoutePlanRequest $request, CreateOrderedRoutePlan $action)
    {
        try {
            $plan = $action->execute($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['route' => $exception->getMessage()]);
        }

        return redirect()->route('route-plans.show', $plan);
    }

    public function show(RoutePlan $routePlan, GoogleMapsRouteUrlBuilder $urlBuilder)
    {
        $this->authorize('view', $routePlan);
        $routePlan->load(['stops' => fn ($q) => $q->orderBy('optimized_order')]);

        return view('route-plans.workspace', ['plan' => $routePlan, 'browserKey' => config('services.google.browser_key'), 'googleMapsLinks' => $urlBuilder->build($routePlan)]);
    }

    public function reorder(Request $request, RoutePlan $routePlan, ReorderRoutePlan $action)
    {
        $this->authorize('update', $routePlan);
        $data = $request->validate(['stop_ids' => ['required', 'array', 'min:2'], 'stop_ids.*' => ['required', 'integer', 'distinct']]);
        try {
            $action->execute($routePlan, $data['stop_ids']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['route' => $exception->getMessage()]);
        }

        return redirect()->route('route-plans.show', $routePlan)->with('status', 'Η διαδρομή ενημερώθηκε με τη σειρά που επιλέξατε.');
    }

    public function storeStop(Request $request, RoutePlan $routePlan, AddRouteStop $action)
    {
        $this->authorize('update', $routePlan);
        $data = $request->validate(['address' => ['required', 'string', 'max:255']]);

        try {
            $action->execute($routePlan, $data['address']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['route' => $exception->getMessage()]);
        }

        return redirect()->route('route-plans.show', $routePlan)->with('status', 'Η στάση προστέθηκε και η διαδρομή υπολογίστηκε ξανά.');
    }

    public function updateStop(Request $request, RoutePlan $routePlan, Stop $stop, UpdateRouteStop $action)
    {
        $this->authorize('update', $routePlan);
        $data = $request->validate(['address' => ['required', 'string', 'max:255']]);

        try {
            $action->execute($routePlan, $stop, $data['address']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['route' => $exception->getMessage()]);
        }

        return redirect()->route('route-plans.show', $routePlan)->with('status', 'Η διεύθυνση ενημερώθηκε και η διαδρομή υπολογίστηκε ξανά.');
    }

    public function destroyStop(RoutePlan $routePlan, Stop $stop, DeleteRouteStop $action)
    {
        $this->authorize('update', $routePlan);
        try {
            $action->execute($routePlan, $stop);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['route' => $exception->getMessage()]);
        }

        return redirect()->route('route-plans.show', $routePlan)->with('status', 'Η στάση διαγράφηκε και η διαδρομή υπολογίστηκε ξανά.');
    }
}
