<?php

namespace App\Http\Controllers;

use App\Models\ApiRequestLog;
use App\Models\RoutePlan;
use App\Models\StopNotification;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $smsEnabled = (bool) config('services.sms.enabled');
        $smsByDay = $smsEnabled
            ? StopNotification::query()
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->pluck('total', 'day')
            : collect();

        $days = collect(range(6, 0))->map(function ($daysAgo) use ($smsByDay) {
            $date = now()->subDays($daysAgo);

            return [
                'label' => $date->format('d/m'),
                'total' => (int) ($smsByDay[$date->format('Y-m-d')] ?? 0),
            ];
        });

        return view('admin.dashboard', [
            'routeCount' => RoutePlan::count(),
            'userCount' => User::where('role', 'user')->count(),
            'smsEnabled' => $smsEnabled,
            'smsToday' => $smsEnabled ? StopNotification::whereDate('created_at', today())->count() : 0,
            'smsAccepted' => $smsEnabled ? StopNotification::whereIn('status', ['accepted', 'delivered'])->count() : 0,
            'smsFailed' => $smsEnabled ? StopNotification::where('status', 'failed')->count() : 0,
            'smsByDay' => $days,
            'maximumSms' => max(1, $days->max('total')),
            'googleCallsToday' => ApiRequestLog::whereDate('requested_at', today())->where('cache_hit', false)->count(),
            'googleFailuresToday' => ApiRequestLog::whereDate('requested_at', today())->where('status', 'failed')->count(),
            'googleAverageMsToday' => (int) round(ApiRequestLog::whereDate('requested_at', today())->where('cache_hit', false)->avg('duration_ms') ?? 0),
            'geocodeCacheHitsToday' => ApiRequestLog::whereDate('requested_at', today())->where('cache_hit', true)->count(),
        ]);
    }

    public function routes(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'mode' => ['nullable', 'in:optimized,ordered'],
        ]);

        $routes = RoutePlan::query()
            ->with(['user', 'stops' => fn ($query) => $query->where('type', 'start')])
            ->withCount(['stops as stops_count' => fn ($query) => $query->where('type', 'visit')])
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($users) => $users->where('email', 'like', "%{$search}%"))
                        ->orWhereHas('stops', fn ($stops) => $stops->where('address', 'like', "%{$search}%"));
                });
            })
            ->when($filters['mode'] ?? null, fn ($query, $mode) => $query->where('route_mode', $mode))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.routes', compact('routes'));
    }

    public function sms(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:queued,sending,retrying,accepted,delivered,failed'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $notifications = StopNotification::query()
            ->with(['user', 'stop.routePlan'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, $search) {
                $digits = preg_replace('/\D+/', '', $search);
                $query->where(function ($query) use ($search, $digits) {
                    $query->where('provider_message_id', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($users) => $users->where('email', 'like', "%{$search}%"))
                        ->orWhereHas('stop.routePlan', fn ($routes) => $routes->where('name', 'like', "%{$search}%"));

                    if ($digits !== '') {
                        $query->orWhere('phone', 'like', "%{$digits}%");
                    }
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.sms', compact('notifications'));
    }

    public function apiUsage(Request $request)
    {
        $filters = $request->validate([
            'service' => ['nullable', 'in:geocoding,routes'],
            'status' => ['nullable', 'in:success,failed'],
        ]);

        $baseQuery = ApiRequestLog::query()
            ->when($filters['service'] ?? null, fn ($query, $service) => $query->where('service', $service))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        $logs = (clone $baseQuery)
            ->with('user')
            ->latest('requested_at')
            ->paginate(30)
            ->withQueryString();

        $todayQuery = (clone $baseQuery)->whereDate('requested_at', today());

        return view('admin.api-usage', [
            'logs' => $logs,
            'requestsToday' => (clone $todayQuery)->where('cache_hit', false)->count(),
            'failuresToday' => (clone $todayQuery)->where('status', 'failed')->count(),
            'averageMsToday' => (int) round((clone $todayQuery)->where('cache_hit', false)->avg('duration_ms') ?? 0),
            'cacheHitsToday' => (clone $todayQuery)->where('cache_hit', true)->count(),
        ]);
    }
}
