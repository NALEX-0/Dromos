@extends('layouts.app')

@section('title', 'Χρώματα διαδρομών — Dromos')

@section('content')
    <div class="map-style-demo route-overlay-demo colored-routes-page">
        <aside class="map-style-panel">
            <div class="colored-routes-title">
                <span class="eyebrow">Dromos</span>
                <h1>Χρώματα διαδρομών</h1>
                <p>Αποθηκεύστε διαδρομές αναφοράς και επιλέξτε ποιες θα εμφανίζονται κατά τη δημιουργία νέας διαδρομής.</p>
            </div>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif
            @if (session('status'))
                <div class="success">{{ session('status') }}</div>
            @endif

            <form method="post" action="{{ route('colored-routes.store') }}" class="route-overlay-form colored-route-editor" id="colored-route-form">
                @csrf
                <input type="hidden" name="_method" id="colored-route-method" value="POST" disabled>

                <label for="overlay-route-name">Όνομα διαδρομής</label>
                <input id="overlay-route-name" name="name" value="{{ old('name') }}" placeholder="π.χ. Πρωινή διαδρομή" maxlength="80" required>

                <label for="overlay-route-addresses">Διευθύνσεις με τη σειρά επίσκεψης</label>
                <textarea id="overlay-route-addresses" name="addresses" placeholder="Θράκης 11, 13671, Αχαρνές&#10;Λεωφόρος Κηφισίας 120, 11526, Αθήνα" required>{{ old('addresses') }}</textarea>

                <span class="map-style-palette-label">Χρώμα διαδρομής</span>
                <div class="map-style-palette" aria-label="Επιλογή χρώματος διαδρομής">
                    @foreach ($palette as $color)
                        <button type="button" class="map-color {{ $loop->first ? 'is-active' : '' }}" data-color="{{ $color }}" aria-label="Χρώμα {{ $color }}" style="--map-color:{{ $color }}"></button>
                    @endforeach
                    <label class="custom-map-color" title="Προσαρμοσμένο χρώμα">
                        <input type="color" id="custom-route-color" value="#2878D0" aria-label="Προσαρμοσμένο χρώμα">
                    </label>
                </div>
                <input type="hidden" id="overlay-route-color" name="color" value="{{ old('color', $palette[0]) }}">

                <div class="colored-route-form-actions">
                    <button type="submit" class="route-overlay-submit" id="colored-route-submit">Αποθήκευση διαδρομής</button>
                    <button type="button" class="dialog-cancel" id="cancel-colored-route-edit" hidden>Ακύρωση επεξεργασίας</button>
                </div>
            </form>

            <div class="colored-areas-heading">
                <strong>Αποθηκευμένες διαδρομές</strong>
                <small>{{ $coloredRoutes->where('is_enabled', true)->count() }} ενεργές</small>
            </div>

            <div class="colored-routes-list">
                @forelse ($coloredRoutes as $coloredRoute)
                    @php
                        $coloredRouteMapData = [
                            'id' => $coloredRoute->id,
                            'name' => $coloredRoute->name,
                            'color' => $coloredRoute->color,
                            'addresses' => $coloredRoute->addresses,
                            'encodedPolyline' => $coloredRoute->encoded_polyline,
                            'path' => $coloredRoute->path,
                            'isCircular' => $coloredRoute->is_circular,
                            'enabled' => $coloredRoute->is_enabled,
                        ];
                    @endphp
                    <article class="colored-route-card {{ $coloredRoute->is_enabled ? '' : 'is-disabled' }}"
                        data-route='@json($coloredRouteMapData)'>
                        <span class="colored-area-swatch" style="background:{{ $coloredRoute->color }};border-color:{{ $coloredRoute->color }}"></span>
                        <div class="colored-route-card-copy">
                            <strong>{{ $coloredRoute->name }}</strong>
                            <small>{{ number_format($coloredRoute->distance_meters / 1000, 1) }} km · {{ max(1, round($coloredRoute->duration_seconds / 60)) }} λεπτά{{ $coloredRoute->is_circular ? ' · Κυκλική' : '' }}</small>
                        </div>
                        <div class="colored-route-card-actions">
                            <form method="post" action="{{ route('colored-routes.toggle', $coloredRoute) }}">
                                @csrf @method('PATCH')
                                <button class="colored-route-toggle {{ $coloredRoute->is_enabled ? 'is-enabled' : '' }}">{{ $coloredRoute->is_enabled ? 'Ενεργή' : 'Ανενεργή' }}</button>
                            </form>
                            <button type="button" class="edit-colored-route">Επεξεργασία</button>
                            <form method="post" action="{{ route('colored-routes.destroy', $coloredRoute) }}" onsubmit="return confirm('Να διαγραφεί αυτή η έγχρωμη διαδρομή;')">
                                @csrf @method('DELETE')
                                <button class="delete-colored-route">Διαγραφή</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="colored-routes-empty">Δεν έχετε αποθηκεύσει έγχρωμες διαδρομές ακόμη.</p>
                @endforelse
            </div>
        </aside>

        <section class="map-style-canvas">
            <div id="route-overlay-map" class="map"></div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        const coloredRouteForm = document.querySelector('#colored-route-form');
        const coloredRouteMethod = document.querySelector('#colored-route-method');
        const coloredRouteName = document.querySelector('#overlay-route-name');
        const coloredRouteAddresses = document.querySelector('#overlay-route-addresses');
        const coloredRouteColor = document.querySelector('#overlay-route-color');
        const coloredRouteSubmit = document.querySelector('#colored-route-submit');
        const cancelColoredRouteEdit = document.querySelector('#cancel-colored-route-edit');
        const colorButtons = document.querySelectorAll('.map-color');
        const customColor = document.querySelector('#custom-route-color');
        const storeAction = @json(route('colored-routes.store'));

        function selectColoredRouteColor(color) {
            coloredRouteColor.value = color.toUpperCase();
            customColor.value = color;
            colorButtons.forEach((button) => button.classList.toggle('is-active', button.dataset.color.toUpperCase() === color.toUpperCase()));
        }

        function resetColoredRouteForm() {
            coloredRouteForm.action = storeAction;
            coloredRouteMethod.disabled = true;
            coloredRouteMethod.value = 'POST';
            coloredRouteName.value = '';
            coloredRouteAddresses.value = '';
            selectColoredRouteColor(@json($palette[0]));
            coloredRouteSubmit.textContent = 'Αποθήκευση διαδρομής';
            cancelColoredRouteEdit.hidden = true;
        }

        colorButtons.forEach((button) => button.addEventListener('click', () => selectColoredRouteColor(button.dataset.color)));
        customColor.addEventListener('input', () => selectColoredRouteColor(customColor.value));
        cancelColoredRouteEdit.addEventListener('click', resetColoredRouteForm);

        document.querySelectorAll('.edit-colored-route').forEach((button) => button.addEventListener('click', () => {
            const route = JSON.parse(button.closest('.colored-route-card').dataset.route);
            coloredRouteForm.action = `${storeAction}/${route.id}`;
            coloredRouteMethod.disabled = false;
            coloredRouteMethod.value = 'PATCH';
            coloredRouteName.value = route.name;
            coloredRouteAddresses.value = route.addresses.join('\n');
            selectColoredRouteColor(route.color);
            coloredRouteSubmit.textContent = 'Αποθήκευση αλλαγών';
            cancelColoredRouteEdit.hidden = false;
            coloredRouteName.focus();
            coloredRouteForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }));

        @if ($browserKey)
        window.initColoredRoutesMap = () => {
            const map = new google.maps.Map(document.querySelector('#route-overlay-map'), {
                center: { lat: 37.9838, lng: 23.7275 }, zoom: 11,
                mapTypeControl: false, streetViewControl: false, fullscreenControl: false,
            });
            const bounds = new google.maps.LatLngBounds();

            document.querySelectorAll('.colored-route-card').forEach((card, index) => {
                const route = JSON.parse(card.dataset.route);
                if (! route.enabled) return;
                const path = route.encodedPolyline ? google.maps.geometry.encoding.decodePath(route.encodedPolyline) : route.path;
                path.forEach((point) => bounds.extend(point));

                if (route.isCircular) {
                    new google.maps.Polygon({
                        map,
                        paths: path,
                        clickable: false,
                        fillColor: route.color,
                        fillOpacity: .12,
                        strokeOpacity: 0,
                        zIndex: index * 3 + 1,
                    });
                }

                new google.maps.Polyline({ map, path, strokeColor: '#ffffff', strokeOpacity: .9, strokeWeight: 11, zIndex: index * 3 + 2 });
                new google.maps.Polyline({ map, path, strokeColor: route.color, strokeOpacity: .95, strokeWeight: 7, zIndex: index * 3 + 3 });
            });

            if (! bounds.isEmpty()) map.fitBounds(bounds, 60);
        };
        @endif
    </script>

    @if ($browserKey)
        <script async src="https://maps.googleapis.com/maps/api/js?key={{ $browserKey }}&libraries=geometry&callback=initColoredRoutesMap"></script>
    @endif
@endpush
