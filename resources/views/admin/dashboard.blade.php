@extends('layouts.app')

@section('title', 'Διαχείριση — Dromos')

@section('content')
    <div class="admin-page">
        <header class="admin-header">
            <div>
                <span class="eyebrow">Dromos Admin</span>
                <h1>Πίνακας διαχείρισης</h1>
                <p>Συνολική εικόνα διαδρομών και χρήσης υπηρεσιών.</p>
            </div>
            <nav class="admin-tabs" aria-label="Ενότητες διαχείρισης">
                <a href="{{ route('admin.routes') }}">Όλες οι διαδρομές</a>
                @if ($smsEnabled)
                    <a href="{{ route('admin.sms') }}">Αρχείο SMS</a>
                @endif
                <a href="{{ route('admin.api-usage') }}">Google API</a>
            </nav>
        </header>

        <div class="admin-stats">
            <article><span>Χρήστες</span><strong>{{ $userCount }}</strong></article>
            <article><span>Διαδρομές</span><strong>{{ $routeCount }}</strong></article>
            @if ($smsEnabled)
                <article><span>SMS σήμερα</span><strong>{{ $smsToday }}</strong></article>
                <article><span>SMS αποδεκτά</span><strong>{{ $smsAccepted }}</strong></article>
                <article><span>SMS αποτυχημένα</span><strong>{{ $smsFailed }}</strong></article>
            @endif
        </div>

        <div class="admin-stats admin-stats-google">
            <article><span>Google κλήσεις σήμερα</span><strong>{{ $googleCallsToday }}</strong></article>
            {{-- <article><span>Μέσος χρόνος</span><strong>{{ $googleAverageMsToday }} ms</strong></article> --}}
            <article><span>Αποτυχίες σήμερα</span><strong>{{ $googleFailuresToday }}</strong></article>
            <article><span>Cache hits σήμερα</span><strong>{{ $geocodeCacheHitsToday }}</strong></article>
        </div>

        @if ($smsEnabled)
        <section class="admin-panel">
            <div class="admin-panel-heading">
                <div>
                    <span class="eyebrow">Τελευταίες 7 ημέρες</span>
                    <h2>Αποστολές SMS ανά ημέρα</h2>
                </div>
                <a href="{{ route('admin.sms') }}">Προβολή αρχείου</a>
            </div>

            <div class="sms-chart" aria-label="Γράφημα SMS ανά ημέρα">
                @foreach ($smsByDay as $day)
                    <div class="sms-chart-column">
                        <span>{{ $day['total'] }}</span>
                        <div style="--bar-height: {{ max(4, ($day['total'] / $maximumSms) * 100) }}%"></div>
                        <small>{{ $day['label'] }}</small>
                    </div>
                @endforeach
            </div>
        </section>
        @endif
    </div>
@endsection
