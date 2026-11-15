@extends('layouts.app')

@section('title', 'Οι διαδρομές μου — Dromos')

@section('content')
    <div class="routes-history">
        <header class="history-header">
            <div>
                <span class="eyebrow">Ιστορικό</span>
                <h1>Οι διαδρομές μου</h1>
                <p>Βρείτε, ανοίξτε και διαχειριστείτε προηγούμενες διαδρομές.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="success">{{ session('status') }}</div>
        @endif

        <form method="get" class="history-filters">
            <label>
                <span>Αναζήτηση</span>
                <input name="q" value="{{ request('q') }}" placeholder="Όνομα ή διεύθυνση">
            </label>
            <label>
                <span>Τύπος</span>
                <select name="mode">
                    <option value="">Όλες</option>
                    <option value="optimized" @selected(request('mode') === 'optimized')>Βελτιστοποιημένες</option>
                    <option value="ordered" @selected(request('mode') === 'ordered')>Σειριακές</option>
                </select>
            </label>
            <button type="submit">Φιλτράρισμα</button>
            @if (request()->hasAny(['q', 'mode']))
                <a href="{{ route('route-plans.index') }}">Καθαρισμός</a>
            @endif
        </form>

        @if ($routes->isEmpty())
            <div class="history-empty">
                <span aria-hidden="true">↗</span>
                <h2>{{ request()->hasAny(['q', 'mode']) ? 'Δεν βρέθηκαν διαδρομές' : 'Δεν έχετε αποθηκευμένες διαδρομές' }}</h2>
                <p>{{ request()->hasAny(['q', 'mode']) ? 'Δοκιμάστε διαφορετικά φίλτρα.' : 'Δημιουργήστε την πρώτη σας διαδρομή για να εμφανιστεί εδώ.' }}</p>
            </div>
        @else
            <div class="history-list">
                @foreach ($routes as $routePlan)
                    @php
                        $origin = $routePlan->stops->first();
                        $minutes = intdiv((int) $routePlan->total_duration_seconds, 60);
                        $hours = intdiv($minutes, 60);
                        $remainingMinutes = $minutes % 60;
                    @endphp
                    <article class="history-route-card">
                        <div class="history-route-main">
                            <div class="history-route-title">
                                <span class="route-mode-icon" aria-hidden="true">{{ $routePlan->route_mode === 'ordered' ? '≡' : '↗' }}</span>
                                <div>
                                    <h2>{{ $routePlan->name }}</h2>
                                    <p>{{ $origin?->address ?: 'Χωρίς σημείο εκκίνησης' }}</p>
                                </div>
                            </div>

                            <div class="history-route-tags">
                                <span>{{ $routePlan->route_mode === 'ordered' ? 'Σειριακή' : 'Βελτιστοποιημένη' }}</span>
                                <span>{{ $routePlan->avoid_tolls ? 'Χωρίς διόδια' : 'Με διόδια' }}</span>
                            </div>
                        </div>

                        <dl class="history-route-metrics">
                            <div><dt>Στάσεις</dt><dd>{{ $routePlan->stops_count }}</dd></div>
                            <div><dt>Απόσταση</dt><dd>{{ number_format($routePlan->total_distance_meters / 1000, 1) }} km</dd></div>
                            <div>
                                <dt>Χρόνος</dt>
                                <dd>{{ $hours > 0 ? $hours.'ω '.($remainingMinutes > 0 ? $remainingMinutes.'λ' : '') : $minutes.' λεπτά' }}</dd>
                            </div>
                            <div><dt>Ενημερώθηκε</dt><dd>{{ $routePlan->updated_at->format('d/m/Y H:i') }}</dd></div>
                        </dl>

                        <div class="history-route-actions">
                            <a href="{{ route('route-plans.show', $routePlan) }}" class="history-open-route">Άνοιγμα</a>
                            @if ($routePlan->share_token)
                                <button
                                    type="button"
                                    class="history-share-route"
                                    data-share-url="{{ route('route-plans.shared', $routePlan->share_token) }}"
                                >Κοινοποίηση</button>
                            @endif
                            <form method="post" action="{{ route('route-plans.destroy', $routePlan) }}" onsubmit="return confirm('Να διαγραφεί οριστικά αυτή η διαδρομή;')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="history-delete-route">Διαγραφή</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="history-pagination">{{ $routes->links() }}</div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-share-url]').forEach((button) => {
            button.addEventListener('click', async () => {
                const originalLabel = button.textContent;

                try {
                    await navigator.clipboard.writeText(button.dataset.shareUrl);
                    button.textContent = 'Αντιγράφηκε';
                } catch {
                    const input = document.createElement('textarea');
                    input.value = button.dataset.shareUrl;
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.append(input);
                    input.select();
                    document.execCommand('copy');
                    input.remove();
                    button.textContent = 'Αντιγράφηκε';
                }

                window.setTimeout(() => {
                    button.textContent = originalLabel;
                }, 1800);
            });
        });
    </script>
@endpush
