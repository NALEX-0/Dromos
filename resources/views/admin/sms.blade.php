@extends('layouts.app')

@section('title', 'Αρχείο SMS — Dromos')

@section('content')
    <div class="admin-page">
        <header class="admin-header">
            <div>
                <span class="eyebrow">Διαχείριση</span>
                <h1>Αρχείο SMS</h1>
                <p>Κατάσταση και τεχνικές πληροφορίες των ειδοποιήσεων.</p>
            </div>
            <a class="admin-back" href="{{ route('admin.dashboard') }}">← Πίνακας διαχείρισης</a>
        </header>

        <form method="get" class="history-filters">
            <label><span>Αναζήτηση</span><input name="q" value="{{ request('q') }}" placeholder="Διαδρομή, email, τηλέφωνο ή ID"></label>
            <label>
                <span>Κατάσταση</span>
                <select name="status">
                    <option value="">Όλες</option>
                    @foreach (['queued', 'sending', 'retrying', 'accepted', 'delivered', 'failed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit">Φιλτράρισμα</button>
        </form>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Χρόνος</th><th>Διαδρομή</th><th>Χρήστης</th><th>Τηλέφωνο</th><th>Κατάσταση</th><th>Provider ID</th><th>Σφάλμα</th></tr></thead>
                <tbody>
                    @forelse ($notifications as $notification)
                        <tr>
                            <td>{{ $notification->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $notification->stop?->routePlan?->name ?: '—' }}</td>
                            <td>{{ $notification->user?->email ?: '—' }}</td>
                            <td>{{ $notification->phone }}</td>
                            <td><span class="sms-status is-{{ $notification->status }}">{{ $notification->status }}</span></td>
                            <td>{{ $notification->provider_message_id ?: '—' }}</td>
                            <td class="admin-error-cell">{{ $notification->failure_reason ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Δεν βρέθηκαν εγγραφές SMS.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="history-pagination">{{ $notifications->links('route-plans.pagination') }}</div>
    </div>
@endsection
