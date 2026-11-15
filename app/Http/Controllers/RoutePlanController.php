<?php

namespace App\Http\Controllers;

use App\Actions\AddRouteStop;
use App\Actions\CreateOptimizedRoutePlan;
use App\Actions\CreateOrderedRoutePlan;
use App\Actions\DeleteRouteStop;
use App\Actions\ReorderRoutePlan;
use App\Actions\UpdateRouteStop;
use App\Actions\UpdateRouteOptions;
use App\Http\Requests\StoreOrderedRoutePlanRequest;
use App\Http\Requests\StoreRoutePlanRequest;
use App\Models\RoutePlan;
use App\Models\Stop;
use App\Services\GoogleMapsRouteUrlBuilder;
use Illuminate\Http\Request;
use Throwable;

class RoutePlanController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'mode' => ['nullable', 'in:optimized,ordered'],
        ]);

        $routes = RoutePlan::query()
            ->where('user_id', $request->user()->id)
            ->with(['stops' => fn ($query) => $query->where('type', 'start')])
            ->withCount(['stops as stops_count' => fn ($query) => $query->where('type', 'visit')])
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('stops', fn ($stops) => $stops->where('address', 'like', "%{$search}%"));
                });
            })
            ->when($filters['mode'] ?? null, fn ($query, $mode) => $query->where('route_mode', $mode))
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString();

        return view('route-plans.index', compact('routes'));
    }

    public function create()
    {
        return view('route-plans.workspace', ['plan' => null, 'browserKey' => config('services.google.browser_key'), 'plannerMode' => 'optimized', 'maximumStops' => 25]);
    }

    public function createOrdered()
    {
        return view('route-plans.workspace', ['plan' => null, 'browserKey' => config('services.google.browser_key'), 'plannerMode' => 'ordered', 'maximumStops' => 100]);
    }

    public function mapStyleDemo()
    {
        return view('map-style-demo', [
            'browserKey' => config('services.google.browser_key'),
        ]);
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
        $routePlan->ensureShareToken();
        $routePlan->load(['stops' => fn ($q) => $q->orderBy('optimized_order')]);

        return view('route-plans.workspace', [
            'plan' => $routePlan,
            'browserKey' => config('services.google.browser_key'),
            'googleMapsLinks' => $urlBuilder->build($routePlan),
            'shareUrl' => route('route-plans.shared', $routePlan->share_token),
        ]);
    }

    public function shared(string $shareToken, GoogleMapsRouteUrlBuilder $urlBuilder)
    {
        $routePlan = RoutePlan::query()
            ->where('share_token', $shareToken)
            ->where('status', 'ready')
            ->firstOrFail();
        $routePlan->load(['stops' => fn ($query) => $query->orderBy('optimized_order')]);

        return view('route-plans.shared', [
            'plan' => $routePlan,
            'browserKey' => config('services.google.browser_key'),
            'googleMapsLinks' => $urlBuilder->build($routePlan),
            'shareUrl' => route('route-plans.shared', $routePlan->share_token),
        ]);
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

    public function updateOptions(Request $request, RoutePlan $routePlan, UpdateRouteOptions $action)
    {
        $this->authorize('update', $routePlan);
        $data = $request->validate(['avoid_tolls' => ['required', 'boolean']]);

        try {
            $action->execute($routePlan, (bool) $data['avoid_tolls']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['route' => $exception->getMessage()]);
        }

        return redirect()
            ->route('route-plans.show', $routePlan)
            ->with('status', 'Οι επιλογές διαδρομής ενημερώθηκαν.');
    }

    public function update(Request $request, RoutePlan $routePlan)
    {
        $this->authorize('update', $routePlan);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $routePlan->update($data);

        return back()->with('status', 'Το όνομα της διαδρομής ενημερώθηκε.');
    }

    public function destroy(RoutePlan $routePlan)
    {
        $this->authorize('delete', $routePlan);
        $routePlan->delete();

        return redirect()->route('route-plans.index')->with('status', 'Η διαδρομή διαγράφηκε.');
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
