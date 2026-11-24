@extends('layouts.app')

@section('title', 'Όλες οι διαδρομές — Dromos')

@section('content')
    <div class="admin-page">
        <header class="admin-header">
            <div>
                <span class="eyebrow">Διαχείριση</span>
                <h1>Όλες οι διαδρομές</h1>
                <p>Πλήρης πρόσβαση στις διαδρομές όλων των χρηστών.</p>
            </div>
            <a class="admin-back" href="{{ route('admin.dashboard') }}">← Πίνακας διαχείρισης</a>
        </header>

        <form method="get" class="history-filters">
            <label><span>Αναζήτηση</span><input name="q" value="{{ request('q') }}" placeholder="Όνομα, email ή διεύθυνση"></label>
            <label>
                <span>Τύπος</span>
                <select name="mode">
                    <option value="">Όλες</option>
                    <option value="optimized" @selected(request('mode') === 'optimized')>Βελτιστοποιημένες</option>
                    <option value="ordered" @selected(request('mode') === 'ordered')>Σειριακές</option>
                </select>
            </label>
            <button type="submit">Φιλτράρισμα</button>
        </form>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Διαδρομή</th><th>Χρήστης</th><th>Τύπος</th><th>Στάσεις</th><th>Ενημερώθηκε</th><th></th></tr></thead>
                <tbody>
                    @forelse ($routes as $routePlan)
                        <tr>
                            <td><strong>{{ $routePlan->name }}</strong></td>
                            <td>{{ $routePlan->user?->email ?: 'Χωρίς χρήστη' }}</td>
                            <td>{{ $routePlan->route_mode === 'ordered' ? 'Σειριακή' : 'Βελτιστοποιημένη' }}</td>
                            <td>{{ $routePlan->stops_count }}</td>
                            <td>{{ $routePlan->updated_at->format('d/m/Y H:i') }}</td>
                            <td><a href="{{ route('route-plans.show', $routePlan) }}">Άνοιγμα</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Δεν βρέθηκαν διαδρομές.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="history-pagination">{{ $routes->links('route-plans.pagination') }}</div>
    </div>
@endsection
