@extends('layouts.app')

@section('title', 'Κοινόχρηστη διαδρομή — Dromos')

@section('content')
    @php
        $origin = $plan->stops->firstWhere('type', 'start');
        $visits = $plan->stops->where('type', 'visit')->values();
        $durationMinutes = intdiv($plan->total_duration_seconds, 60);
        $durationHours = intdiv($durationMinutes, 60);
        $remainingMinutes = $durationMinutes % 60;
    @endphp

    <div class="workspace-grid shared-route">
        <aside class="workspace-sidebar">
            <div class="workspace-heading">
                <div>
                    <span class="eyebrow">Dromos</span>
                    <h1>Κοινόχρηστη διαδρομή</h1>
                </div>
            </div>

            <div class="shared-route-note">Μόνο για προβολή · η διαδρομή δεν μπορεί να τροποποιηθεί</div>

            <div class="mini-summary">
                <div>
                    <span>Απόσταση</span>
                    <b>{{ number_format($plan->total_distance_meters / 1000, 1) }} km</b>
                </div>
                <div>
                    <span>Χρόνος διαδρομής</span>
                    <b>
                        @if ($durationHours > 0)
                            {{ $durationHours }} {{ $durationHours === 1 ? 'ώρα' : 'ώρες' }}@if ($remainingMinutes > 0) {{ $remainingMinutes }} λεπτά@endif
                        @else
                            {{ $durationMinutes }} λεπτά
                        @endif
                    </b>
                </div>
            </div>

            @if ($plan->avoid_tolls)
                <div class="route-constraint">✓ Αποφυγή διοδίων ενεργή</div>
            @endif

            @if ($plan->route_mode === 'ordered')
                <div class="route-constraint ordered-mode-badge">Σειριακή διαδρομή · χωρίς βελτιστοποίηση σειράς</div>
            @endif

            <div class="share-route">
                <div class="share-route-links" style="--segment-count: {{ min(count($googleMapsLinks), 3) }}">
                    @foreach ($googleMapsLinks as $googleMapsLink)
                        <a href="{{ $googleMapsLink }}" target="_blank" rel="noopener" class="google-maps-link">
                            {{ count($googleMapsLinks) === 1 ? 'Άνοιγμα στο Google Maps' : 'Άνοιγμα τμήματος '.$loop->iteration }}
                        </a>
                    @endforeach
                </div>
                <button type="button" id="share-dromos-route" data-url="{{ $shareUrl }}">
                    Κοινοποίηση διαδρομής
                </button>
            </div>

            <div class="fixed-origin">
                <span class="origin-dot">S</span>
                <div>
                    <small>Έναρξη</small>
                    <strong>{{ $origin->address }}</strong>
                </div>
                <span class="fixed-label">Σταθερό</span>
            </div>

            <div id="ordered-stops" class="ordered-list">
                @foreach ($visits as $stop)
                    @php
                        $googleMapsLocation = $stop->formatted_address ?: $stop->address;
                        $stopGoogleMapsUrl = 'https://www.google.com/maps/search/?'.http_build_query([
                            'api' => 1,
                            'query' => $googleMapsLocation,
                        ], '', '&', PHP_QUERY_RFC3986);
                    @endphp
                    <article class="ordered-stop shared-ordered-stop" data-stop-id="{{ $stop->id }}">
                        <span class="stop-number">{{ $stop->optimized_order }}</span>
                        <div class="stop-copy">
                            <strong>{{ $stop->address }}</strong>
                            <small>{{ $stop->formatted_address }}</small>
                            <small class="leg">
                                {{ round($stop->leg_distance_meters / 1000, 1) }} km ·
                                {{ max(1, round($stop->leg_duration_seconds / 60)) }} min
                            </small>
                            <a
                                href="{{ $stopGoogleMapsUrl }}"
                                target="_blank"
                                rel="noopener"
                                class="share-stop"
                            >Άνοιγμα</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </aside>

        <section class="workspace-map">
            <span class="demo-pill">Κοινόχρηστη διαδρομή</span>
            <div id="map" class="map">
                <div class="map-fallback">
                    <div>
                        <span class="map-logo" aria-hidden="true"><x-icons.car :size="24" /></span>
                        <b>Χάρτης διαδρομής</b>
                        <span>Συνδέστε το κλειδί χάρτη για να δείτε τον διαδραστικό χάρτη.</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelector('#share-dromos-route').addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const originalLabel = button.textContent;

            if (navigator.share) {
                try {
                    await navigator.share({
                        title: 'Διαδρομή Dromos',
                        text: 'Δείτε την υπολογισμένη διαδρομή στο Dromos.',
                        url: button.dataset.url,
                    });

                    return;
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                }
            }

            await navigator.clipboard.writeText(button.dataset.url);
            button.textContent = 'Ο σύνδεσμος αντιγράφηκε!';
            setTimeout(() => button.textContent = originalLabel, 1800);
        });
    </script>

    @if ($browserKey)
        @php
            $mapStops = $plan->stops->map(fn ($stop) => [
                'id' => $stop->id,
                'lat' => $stop->latitude,
                'lng' => $stop->longitude,
                'label' => $stop->optimized_order === 0 ? 'S' : (string) $stop->optimized_order,
                'isStart' => $stop->type === 'start',
                'address' => $stop->formatted_address ?: $stop->address,
                'googleMapsUrl' => 'https://www.google.com/maps/search/?'.http_build_query([
                    'api' => 1,
                    'query' => $stop->formatted_address ?: $stop->address,
                ]),
            ]);
        @endphp

        <script>
            window.dromosStops = @json($mapStops);
            window.dromosPolylines = @json($plan->encoded_polylines ?: array_values(array_filter([$plan->encoded_polyline])));

            window.initDromosMap = () => {
                const markerIcon = (highlighted = false, isStart = false) => ({
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: highlighted ? 23 : 18,
                    fillColor: highlighted ? '#b8df45' : (isStart ? '#5f9b72' : '#1f5c43'),
                    fillOpacity: 1,
                    strokeColor: highlighted ? '#1f5c43' : '#ffffff',
                    strokeWeight: highlighted ? 3 : 2,
                });
                const map = new google.maps.Map(document.querySelector('#map'), {
                    center: { lat: 37.9838, lng: 23.7275 },
                    zoom: 12,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: false,
                });
                const bounds = new google.maps.LatLngBounds();
                const markers = new Map();

                window.dromosStops.forEach((stop) => {
                    const marker = new google.maps.Marker({
                        map,
                        position: stop,
                        label: { text: stop.label, color: 'white', fontWeight: '700' },
                        icon: markerIcon(false, stop.isStart),
                        title: stop.address,
                    });

                    marker.addListener('click', () => {
                        window.open(stop.googleMapsUrl, '_blank', 'noopener,noreferrer');
                    });

                    markers.set(String(stop.id), marker);
                    bounds.extend(stop);
                });

                document.querySelector('#ordered-stops').addEventListener('mouseover', (event) => {
                    const row = event.target.closest('.ordered-stop');
                    const marker = row && markers.get(row.dataset.stopId);

                    if (! marker || row.contains(event.relatedTarget)) {
                        return;
                    }

                    marker.setIcon(markerIcon(true));
                    marker.setLabel({ text: marker.getLabel().text, color: '#1f5c43', fontWeight: '800' });
                    marker.setZIndex(google.maps.Marker.MAX_ZINDEX + 1);
                    row.classList.add('is-map-highlighted');
                });

                document.querySelector('#ordered-stops').addEventListener('mouseout', (event) => {
                    const row = event.target.closest('.ordered-stop');
                    const marker = row && markers.get(row.dataset.stopId);

                    if (! marker || row.contains(event.relatedTarget)) {
                        return;
                    }

                    marker.setIcon(markerIcon(false, false));
                    marker.setLabel({ text: marker.getLabel().text, color: 'white', fontWeight: '700' });
                    marker.setZIndex(undefined);
                    row.classList.remove('is-map-highlighted');
                });

                window.dromosPolylines.forEach((encodedPolyline) => {
                    new google.maps.Polyline({
                        map,
                        path: google.maps.geometry.encoding.decodePath(encodedPolyline),
                        strokeColor: '#1f5c43',
                        strokeWeight: 5,
                    });
                });

                map.fitBounds(bounds, 60);
            };
        </script>

        <script
            async
            src="https://maps.googleapis.com/maps/api/js?key={{ $browserKey }}&libraries=geometry&callback=initDromosMap"
        ></script>
    @endif
@endpush
