<?php

namespace App\Http\Controllers;

use App\Contracts\Geocoder;
use App\Contracts\RouteOptimizer;
use App\Models\ColoredRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ColoredRouteController extends Controller
{
    public function index(Request $request): View
    {
        return view('colored-routes.index', [
            'coloredRoutes' => ColoredRoute::query()->latest()->get(),
            'browserKey' => config('services.google.browser_key'),
            'palette' => $this->palette(),
        ]);
    }

    public function store(Request $request, Geocoder $geocoder, RouteOptimizer $routeOptimizer): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $request->user()->coloredRoutes()->create($this->calculate($data, $geocoder, $routeOptimizer));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['colored_route' => 'Η διαδρομή δεν μπόρεσε να υπολογιστεί: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Η έγχρωμη διαδρομή αποθηκεύτηκε.');
    }

    public function update(Request $request, ColoredRoute $coloredRoute, Geocoder $geocoder, RouteOptimizer $routeOptimizer): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $coloredRoute->update($this->calculate($data, $geocoder, $routeOptimizer));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['colored_route' => 'Η διαδρομή δεν μπόρεσε να ενημερωθεί: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Η έγχρωμη διαδρομή ενημερώθηκε.');
    }

    public function toggle(Request $request, ColoredRoute $coloredRoute): RedirectResponse
    {
        $coloredRoute->update(['is_enabled' => ! $coloredRoute->is_enabled]);

        return back()->with('status', $coloredRoute->is_enabled ? 'Η διαδρομή ενεργοποιήθηκε.' : 'Η διαδρομή απενεργοποιήθηκε.');
    }

    public function destroy(Request $request, ColoredRoute $coloredRoute): RedirectResponse
    {
        $coloredRoute->delete();

        return back()->with('status', 'Η έγχρωμη διαδρομή διαγράφηκε.');
    }

    private function validated(Request $request): array
    {
        $request->merge([
            'addresses' => collect(preg_split('/\R/u', (string) $request->input('addresses')))
                ->map(fn (string $address) => trim($address))
                ->filter()
                ->values()
                ->all(),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'addresses' => ['required', 'array', 'min:2', 'max:25'],
            'addresses.*' => ['required', 'string', 'max:255'],
        ]);
    }

    private function calculate(array $data, Geocoder $geocoder, RouteOptimizer $routeOptimizer): array
    {
        $normalize = fn (string $address) => preg_replace('/[\s,]+/u', ' ', mb_strtolower(trim($address)));
        $isCircular = count($data['addresses']) >= 3
            && $normalize($data['addresses'][0]) === $normalize($data['addresses'][array_key_last($data['addresses'])]);
        $points = collect($data['addresses'])->map(fn (string $address) => $geocoder->geocode($address))->values();
        $origin = $points->shift();
        $result = $routeOptimizer->routeInOrder($origin, $points->all());

        return [
            'name' => $data['name'],
            'color' => strtoupper($data['color']),
            'addresses' => $data['addresses'],
            'encoded_polyline' => $result->encodedPolyline,
            'path' => $result->encodedPolyline ? null : $points->prepend($origin)
                ->map(fn ($point) => ['lat' => $point->latitude, 'lng' => $point->longitude])
                ->values()->all(),
            'distance_meters' => $result->distanceMeters,
            'duration_seconds' => $result->durationSeconds,
            'is_circular' => $isCircular,
        ];
    }

    private function palette(): array
    {
        return ['#2878D0', '#D98628', '#1F8A5B', '#8B5BC0', '#E6392F', '#FF21DE', '#00A6A6', '#F2C14E', '#364FC7', '#795548', '#111827', '#E91E63'];
    }
}
