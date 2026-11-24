@extends('layouts.app')

@section('title', 'Google API — Dromos')

@section('content')
    <div class="admin-page">
        <header class="admin-header">
            <div>
                <span class="eyebrow">Διαχείριση</span>
                <h1>Google API</h1>
                <p>Κλήσεις από τον server, χρόνοι απόκρισης, σφάλματα και χρήση της cache.</p>
            </div>
            <a class="admin-back" href="{{ route('admin.dashboard') }}">← Πίνακας διαχείρισης</a>
        </header>

        <div class="admin-stats admin-stats-google">
            <article><span>Κλήσεις σήμερα</span><strong>{{ $requestsToday }}</strong></article>
            <article><span>Μέσος χρόνος</span><strong>{{ $averageMsToday }} ms</strong></article>
            <article><span>Αποτυχίες</span><strong>{{ $failuresToday }}</strong></article>
            <article><span>Cache hits</span><strong>{{ $cacheHitsToday }}</strong></article>
        </div>

        <form method="get" class="history-filters">
            <label>
                <span>Υπηρεσία</span>
                <select name="service">
                    <option value="">Όλες</option>
                    <option value="geocoding" @selected(request('service') === 'geocoding')>Geocoding</option>
                    <option value="routes" @selected(request('service') === 'routes')>Routes</option>
                </select>
            </label>
            <label>
                <span>Κατάσταση</span>
                <select name="status">
                    <option value="">Όλες</option>
                    <option value="success" @selected(request('status') === 'success')>Επιτυχία</option>
                    <option value="failed" @selected(request('status') === 'failed')>Αποτυχία</option>
                </select>
            </label>
            <button type="submit">Φιλτράρισμα</button>
        </form>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Χρόνος</th><th>Υπηρεσία</th><th>Ενέργεια</th><th>Κατάσταση</th><th>HTTP</th><th>Διάρκεια</th><th>Cache</th><th>Χρήστης</th><th>Σφάλμα</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->requested_at->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $log->service }}</td>
                            <td>{{ $log->operation }}</td>
                            <td><span class="sms-status is-{{ $log->status === 'failed' ? 'failed' : 'accepted' }}">{{ $log->status }}</span></td>
                            <td>{{ $log->http_status ?? '—' }}</td>
                            <td>{{ $log->duration_ms }} ms</td>
                            <td>{{ $log->cache_hit ? 'Ναι' : 'Όχι' }}</td>
                            <td>{{ $log->user?->email ?? '—' }}</td>
                            <td class="admin-error-cell">{{ $log->error_message ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">Δεν υπάρχουν ακόμη καταγεγραμμένες κλήσεις.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="history-pagination">{{ $logs->links('route-plans.pagination') }}</div>
    </div>
@endsection
