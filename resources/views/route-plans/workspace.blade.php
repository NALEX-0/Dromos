@extends('layouts.app')

@section('title', 'Dromos — Σχεδιασμός διαδρομής')

@section('content')
    @php
        $plannerMode = $plannerMode ?? $plan?->route_mode ?? 'optimized';
        $maximumStops = $maximumStops ?? ($plannerMode === 'ordered' ? 100 : 25);
        $isOrderedPlanner = $plannerMode === 'ordered';
    @endphp
    <div class="workspace-grid">
        <aside class="workspace-sidebar">
            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            @if (session('status'))
                <div class="success">{{ session('status') }}</div>
            @endif

            @if (! $plan)
                <div class="workspace-heading">
                    <div>
                        <span class="eyebrow">{{ $isOrderedPlanner ? 'Έως 100 στάσεις' : 'Νέα διαδρομή' }}</span>
                        <h1>{{ $isOrderedPlanner ? 'Σειριακή διαδρομή' : 'Σχεδιασμός διαδρομής' }}</h1>
                        <p>{{ $isOrderedPlanner ? 'Η σειρά που εισάγετε διατηρείται, χωρίς αυτόματη βελτιστοποίηση.' : 'Προσθέστε όλες τις στάσεις και θα βρούμε την ταχύτερη σειρά.' }}</p>
                    </div>
                </div>

                <form method="post" action="{{ $isOrderedPlanner ? route('ordered-route-plans.store') : route('route-plans.store') }}">
                    @csrf

                    <button type="button" class="button button-wide import-addresses-button" id="open-import-addresses">
                        Εισαγωγή διευθύνσεων
                        <span>⇧</span>
                    </button>

                    <label class="section-label">Σημείο εκκίνησης</label>

                    <div class="compact-address">
                        <span class="origin-dot">S</span>

                        <div>
                            <input
                                name="start[address]"
                                value="{{ old('start.address', 'Θράκης 11, 13671, Αχαρνές') }}"
                                placeholder="Οδός και αριθμός, Τ.Κ., πόλη"
                                required
                            >
                        </div>
                    </div>

                    <div class="section-row">
                        <label class="section-label">Στάσεις</label>
                        <span id="stop-count"></span>
                    </div>

                    @php
                        $initialStops = old('stops', [
                            ['address' => ''],
                            ['address' => ''],
                        ]);
                    @endphp

                    <div id="stops" class="address-list">
                        @foreach ($initialStops as $index => $stop)
                            <div class="compact-address">
                                <span class="input-number">{{ $index + 1 }}</span>

                                <div>
                                    <input
                                        name="stops[{{ $index }}][address]"
                                        value="{{ $stop['address'] ?? '' }}"
                                        placeholder="Οδός και αριθμός, Τ.Κ., πόλη"
                                        required
                                    >
                                </div>

                                <button type="button" class="remove" aria-label="Αφαίρεση στάσης">×</button>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="add" id="add-stop">
                        ＋ Προσθήκη στάσης
                    </button>

                    <label class="check route-option">
                        <input
                            type="checkbox"
                            name="return_to_start"
                            value="1"
                            @checked(old('return_to_start'))
                        >
                        Επιστροφή στο σημείο εκκίνησης
                    </label>

                    <label class="route-switch">
                        <span>Αποφυγή διοδίων</span>
                        <input
                            type="checkbox"
                            name="avoid_tolls"
                            value="1"
                            @checked(old('avoid_tolls'))
                        >
                        <span class="switch-track" aria-hidden="true"></span>
                    </label>

                    <button class="button button-wide">
                        {{ $isOrderedPlanner ? 'Υπολογισμός σειριακής διαδρομής' : 'Βελτιστοποίηση διαδρομής' }}
                        <span>→</span>
                    </button>
                </form>

                <dialog id="import-addresses-dialog" class="stop-dialog import-dialog">
                    <h2>Εισαγωγή διευθύνσεων</h2>
                    <label for="import-addresses">Μία πλήρης διεύθυνση ανά γραμμή</label>
                    <textarea id="import-addresses" placeholder=""></textarea>
                    <p class="import-help">Επιτρέπονται έως {{ $maximumStops }} διευθύνσεις.</p>
                    <p class="import-error" id="import-error" hidden></p>
                    <div class="dialog-actions">
                        <button type="button" class="dialog-cancel">Ακύρωση</button>
                        <button type="button" class="button" id="apply-import-addresses">Εισαγωγή</button>
                    </div>
                </dialog>
            @else
                @php
                    $origin = $plan->stops->firstWhere('type', 'start');
                    $visits = $plan->stops->where('type', 'visit')->values();
                    $durationMinutes = intdiv($plan->total_duration_seconds, 60);
                    $durationHours = intdiv($durationMinutes, 60);
                    $remainingMinutes = $durationMinutes % 60;
                @endphp

                <div class="workspace-heading">
                    <div>
                        <!-- <span class="eyebrow">Optimized route</span> -->
                        <h1>Διαδρομές</h1>
                        <!-- <p>Move stops or choose an exact position, then recalculate.</p> -->
                    </div>

                    <a href="{{ $plan->route_mode === 'ordered' ? route('ordered-route-plans.create') : route('route-plans.create') }}" class="icon-link" title="Νέα διαδρομή">＋</a>
                </div>

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
                    <div class="copy-route-links" style="--segment-count: {{ min(count($googleMapsLinks), 3) }}">
                        @foreach ($googleMapsLinks as $googleMapsLink)
                            <button type="button" class="copy-google-link" data-link="{{ $googleMapsLink }}">
                                {{ count($googleMapsLinks) === 1 ? 'Αντιγραφή συνδέσμου' : 'Αντιγραφή τμήματος '.$loop->iteration }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="fixed-origin">
                    <span class="origin-dot">S</span>
                    <div>
                        <small>Έναρξη</small>
                        <strong>{{ $origin->address }}</strong>
                    </div>
                    <span class="fixed-label">Σταθερό</span>
                </div>

                <form method="post" action="{{ route('route-plans.reorder', $plan) }}">
                    @csrf
                    @method('PATCH')

                    <div id="ordered-stops" class="ordered-list">
                        @foreach ($visits as $stop)
                            <article class="ordered-stop" draggable="true" data-stop-id="{{ $stop->id }}">
                                <input type="hidden" name="stop_ids[]" value="{{ $stop->id }}">
                                <span class="drag-handle" title="Σύρετε για αλλαγή σειράς" aria-hidden="true">⠿</span>
                                <span class="stop-number">{{ $stop->optimized_order }}</span>

                                <div class="stop-copy">
                                    <strong>{{ $stop->address }}</strong>
                                    <small>{{ $stop->formatted_address }}</small>
                                    <small class="leg">
                                        {{ round($stop->leg_distance_meters / 1000, 1) }} km ·
                                        {{ max(1, round($stop->leg_duration_seconds / 60)) }} min
                                    </small>
                                    <span class="stop-actions">
                                        <button type="button" class="edit-stop" data-address="{{ $stop->address }}" data-action="{{ route('route-plans.stops.update', [$plan, $stop]) }}">Επεξεργασία</button>
                                        <button type="button" class="delete-stop" data-action="{{ route('route-plans.stops.destroy', [$plan, $stop]) }}">Διαγραφή</button>
                                    </span>
                                </div>

                                <div class="order-tools">
                                    <button type="button" class="move-up" aria-label="Μετακίνηση επάνω">↑</button>
                                    <button type="button" class="move-down" aria-label="Μετακίνηση κάτω">↓</button>

                                    <select class="position" aria-label="Ορισμός θέσης">
                                        @foreach ($visits as $position => $unused)
                                            <option
                                                value="{{ $position }}"
                                                @selected($position === $loop->parent->index)
                                            >
                                                #{{ $position + 1 }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <button type="button" class="add add-route-stop" id="open-add-stop">
                        ＋ Προσθήκη στάσης
                    </button>

                    <button class="button button-wide save-order" disabled>
                        Επαναϋπολογισμός διαδρομής
                        <span>→</span>
                    </button>
                </form>

                <dialog id="add-stop-dialog" class="stop-dialog">
                    <form method="post" action="{{ route('route-plans.stops.store', $plan) }}">
                        @csrf
                        <h2>Προσθήκη στάσης</h2>
                        <label for="add-stop-address">Πλήρης διεύθυνση</label>
                        <input id="add-stop-address" name="address" placeholder="Οδός και αριθμός, Τ.Κ., πόλη" required maxlength="255">
                        <div class="dialog-actions">
                            <button type="button" class="dialog-cancel">Ακύρωση</button>
                            <button class="button">Προσθήκη</button>
                        </div>
                    </form>
                </dialog>

                <dialog id="edit-stop-dialog" class="stop-dialog">
                    <form method="post" id="edit-stop-form">
                        @csrf
                        @method('PATCH')
                        <h2>Ενημέρωση διεύθυνσης</h2>
                        <label for="edit-stop-address">Πλήρης διεύθυνση</label>
                        <input id="edit-stop-address" name="address" required maxlength="255">
                        <div class="dialog-actions">
                            <button type="button" class="dialog-cancel">Ακύρωση</button>
                            <button class="button">Αποθήκευση</button>
                        </div>
                    </form>
                </dialog>

                <form method="post" id="delete-stop-form" hidden>
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </aside>

        <section class="workspace-map">
            <span class="demo-pill">
                @if (! $plan)
                    Προεπισκόπηση διαδρομής
                @elseif ($plan->provider === 'demo')
                    Ενδεικτικές εκτιμήσεις
                @else
                    Διαδρομή με ζωντανή κίνηση
                @endif
            </span>

            <div id="map" class="map">
                <div class="map-fallback">
                    <div>
                        <span class="map-logo">↗</span>
                        <b>{{ $plan ? 'Χάρτης διαδρομής' : 'Η διαδρομή σας θα εμφανιστεί εδώ' }}</b>
                        <span>
                            {{ $plan
                                ? 'Συνδέστε το κλειδί χάρτη για να δείτε τον διαδραστικό χάρτη.'
                                : 'Προσθέστε τις στάσεις σας και ξεκινήστε τη βελτιστοποίηση.' }}
                        </span>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <template id="stop-template">
        <div class="compact-address">
            <span class="input-number"></span>
            <div>
                <input data-name="address" placeholder="Οδός και αριθμός, Τ.Κ., πόλη" required>
            </div>
            <button type="button" class="remove" aria-label="Αφαίρεση στάσης">×</button>
        </div>
    </template>
@endsection

@push('scripts')
    @if (! $plan)
        <script>
            const stopList = document.querySelector('#stops');
            const stopTemplate = document.querySelector('#stop-template');
            const stopCount = document.querySelector('#stop-count');
            const importDialog = document.querySelector('#import-addresses-dialog');
            const importTextarea = document.querySelector('#import-addresses');
            const importError = document.querySelector('#import-error');

            function createStopRow(address = '') {
                const row = stopTemplate.content.cloneNode(true);
                row.querySelector('[data-name="address"]').value = address;

                return row;
            }

            function renumberStops() {
                Array.from(stopList.children).forEach((row, index) => {
                    row.querySelector('.input-number').textContent = index + 1;

                    row.querySelectorAll('[data-name]').forEach((input) => {
                        input.name = `stops[${index}][${input.dataset.name}]`;
                    });
                });

                stopCount.textContent = `${stopList.children.length} στάσεις`;
            }

            document.querySelector('#add-stop').addEventListener('click', () => {
                if (stopList.children.length >= {{ $maximumStops }}) {
                    return;
                }

                stopList.append(createStopRow());
                renumberStops();
            });

            document.querySelector('#open-import-addresses').addEventListener('click', () => {
                importTextarea.value = '';
                importError.hidden = true;
                importDialog.showModal();
                importTextarea.focus();
            });

            importDialog.querySelector('.dialog-cancel').addEventListener('click', () => importDialog.close());

            document.querySelector('#apply-import-addresses').addEventListener('click', () => {
                const addresses = importTextarea.value
                    .split(/\r?\n/)
                    .map((address) => address.trim())
                    .filter(Boolean);

                if (addresses.length < 2 || addresses.length > {{ $maximumStops }}) {
                    importError.textContent = 'Εισαγάγετε από 2 έως {{ $maximumStops }} διευθύνσεις, μία σε κάθε γραμμή.';
                    importError.hidden = false;
                    return;
                }

                stopList.replaceChildren(...addresses.map((address) => createStopRow(address)));
                renumberStops();
                importDialog.close();
            });

            stopList.addEventListener('click', (event) => {
                if (! event.target.matches('.remove') || stopList.children.length <= 2) {
                    return;
                }

                event.target.closest('.compact-address').remove();
                renumberStops();
            });

            renumberStops();
        </script>
    @else
        <script>
            const orderedStops = document.querySelector('#ordered-stops');
            const orderForm = orderedStops.closest('form');
            const saveOrderButton = document.querySelector('.save-order');
            const addStopDialog = document.querySelector('#add-stop-dialog');
            const editStopDialog = document.querySelector('#edit-stop-dialog');
            const editStopForm = document.querySelector('#edit-stop-form');
            const editStopAddress = document.querySelector('#edit-stop-address');
            const deleteStopForm = document.querySelector('#delete-stop-form');
            let draggedStop = null;
            let orderBeforeDragging = null;
            let orderSubmissionStarted = false;

            function currentStopOrder() {
                return Array.from(orderedStops.querySelectorAll('[name="stop_ids[]"]'))
                    .map((input) => input.value)
                    .join(',');
            }

            function submitUpdatedOrder() {
                if (orderSubmissionStarted) {
                    return;
                }

                orderSubmissionStarted = true;
                saveOrderButton.disabled = true;
                saveOrderButton.firstChild.textContent = 'Επαναϋπολογισμός... ';
                orderForm.requestSubmit();
            }

            document.querySelectorAll('.copy-google-link').forEach((button) => button.addEventListener('click', async () => {
                const originalLabel = button.textContent;
                await navigator.clipboard.writeText(button.dataset.link);
                button.textContent = 'Αντιγράφηκε!';
                setTimeout(() => button.textContent = originalLabel, 1800);
            }));

            document.querySelector('#open-add-stop').addEventListener('click', () => {
                addStopDialog.showModal();
                addStopDialog.querySelector('input').focus();
            });

            addStopDialog.querySelector('.dialog-cancel').addEventListener('click', () => addStopDialog.close());

            function refreshStopOrder() {
                Array.from(orderedStops.children).forEach((row, index) => {
                    row.querySelector('.stop-number').textContent = index + 1;
                    row.querySelector('.position').value = index;
                    row.querySelector('.move-up').disabled = index === 0;
                    row.querySelector('.move-down').disabled = index === orderedStops.children.length - 1;
                });

                saveOrderButton.disabled = false;
            }

            orderedStops.addEventListener('click', (event) => {
                const row = event.target.closest('.ordered-stop');
                let orderChanged = false;

                if (! row) {
                    return;
                }

                if (event.target.matches('.move-up') && row.previousElementSibling) {
                    orderedStops.insertBefore(row, row.previousElementSibling);
                    refreshStopOrder();
                    orderChanged = true;
                }

                if (event.target.matches('.move-down') && row.nextElementSibling) {
                    orderedStops.insertBefore(row.nextElementSibling, row);
                    refreshStopOrder();
                    orderChanged = true;
                }

                if (orderChanged) {
                    submitUpdatedOrder();
                }

                if (event.target.matches('.edit-stop')) {
                    editStopForm.action = event.target.dataset.action;
                    editStopAddress.value = event.target.dataset.address;
                    editStopDialog.showModal();
                    editStopAddress.focus();
                }

                if (event.target.matches('.delete-stop') && confirm('Να διαγραφεί αυτή η στάση;')) {
                    deleteStopForm.action = event.target.dataset.action;
                    deleteStopForm.submit();
                }
            });

            editStopDialog.querySelector('.dialog-cancel').addEventListener('click', () => editStopDialog.close());

            orderedStops.addEventListener('change', (event) => {
                if (! event.target.matches('.position')) {
                    return;
                }

                const rows = Array.from(orderedStops.children);
                const row = event.target.closest('.ordered-stop');
                const currentPosition = rows.indexOf(row);
                const nextPosition = Number(event.target.value);
                const target = rows[nextPosition];

                if (nextPosition > currentPosition) {
                    target.after(row);
                } else {
                    target.before(row);
                }

                refreshStopOrder();
                submitUpdatedOrder();
            });

            orderedStops.addEventListener('dragstart', (event) => {
                draggedStop = event.target.closest('.ordered-stop');

                if (! draggedStop) {
                    return;
                }

                draggedStop.classList.add('is-dragging');
                orderBeforeDragging = currentStopOrder();
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', draggedStop.querySelector('[name="stop_ids[]"]').value);
            });

            orderedStops.addEventListener('dragover', (event) => {
                event.preventDefault();

                if (! draggedStop) {
                    return;
                }

                const hoveredStop = event.target.closest('.ordered-stop');

                if (! hoveredStop || hoveredStop === draggedStop) {
                    return;
                }

                const hoveredMiddle = hoveredStop.getBoundingClientRect().top + hoveredStop.offsetHeight / 2;
                orderedStops.insertBefore(draggedStop, event.clientY < hoveredMiddle ? hoveredStop : hoveredStop.nextElementSibling);
            });

            orderedStops.addEventListener('drop', (event) => {
                event.preventDefault();
            });

            orderedStops.addEventListener('dragend', () => {
                draggedStop?.classList.remove('is-dragging');
                draggedStop = null;

                if (orderBeforeDragging !== currentStopOrder()) {
                    refreshStopOrder();
                    submitUpdatedOrder();
                }

                orderBeforeDragging = null;
            });

            refreshStopOrder();
            saveOrderButton.disabled = true;
        </script>
    @endif

    @if ($plan && $browserKey)
        @php
            $mapStops = $plan->stops->map(fn ($stop) => [
                'id' => $stop->id,
                'lat' => $stop->latitude,
                'lng' => $stop->longitude,
                'label' => $stop->optimized_order === 0 ? 'S' : (string) $stop->optimized_order,
                'isStart' => $stop->type === 'start',
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
                    });

                    markers.set(String(stop.id), marker);
                    bounds.extend(stop);
                });

                const orderedStopList = document.querySelector('#ordered-stops');

                orderedStopList?.addEventListener('mouseover', (event) => {
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

                orderedStopList?.addEventListener('mouseout', (event) => {
                    const row = event.target.closest('.ordered-stop');
                    const marker = row && markers.get(row.dataset.stopId);

                    if (! marker || row.contains(event.relatedTarget)) {
                        return;
                    }

                    marker.setIcon(markerIcon());
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
