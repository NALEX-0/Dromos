@extends('layouts.app')

@section('title', 'Δοκιμή χρωμάτων χάρτη — Dromos')

@section('content')
    <div class="map-style-demo">
        <aside class="map-style-panel">
            <h1>Χρωματισμός περιοχών</h1>
            <p>
                Το περιβάλλον είναι δοκιμαστικό και οι αλλαγές ΔΕΝ αποθηκεύονται !!!
            </p>

            <form id="area-search-form" class="area-search-form">
                <label for="area-search">Αναζήτηση περιοχής</label>
                <div>
                    <input id="area-search" name="area" value="Πειραιάς" placeholder="π.χ. Γλυφάδα" required>
                    <button type="submit">Αναζήτηση</button>
                </div>
            </form>

            <span class="map-style-palette-label">Χρώμα νέας περιοχής</span>
            <div class="map-style-palette" aria-label="Επιλογή χρώματος νέας περιοχής">
                <button type="button" class="map-color is-active" data-color="#2878d0" data-fill="#dbeafe" aria-label="Μπλε" style="--map-color:#2878d0"></button>
                <button type="button" class="map-color" data-color="#d98628" data-fill="#ffedd5" aria-label="Πορτοκαλί" style="--map-color:#d98628"></button>
                <button type="button" class="map-color" data-color="#1f8a5b" data-fill="#dcfce7" aria-label="Πράσινο" style="--map-color:#1f8a5b"></button>
                <button type="button" class="map-color" data-color="#8b5bc0" data-fill="#f3e8ff" aria-label="Μωβ" style="--map-color:#8b5bc0"></button>
            </div>

            <div class="colored-areas-heading">
                <strong>Χρωματισμένες περιοχές</strong>
                <small>Μπορείτε να προσθέσετε όσες θέλετε</small>
            </div>
            <div id="colored-areas-list" class="colored-areas-list"></div>

        </aside>

        <section class="map-style-canvas">
            <div id="map-style-map" class="map"></div>
        </section>
    </div>
@endsection

@push('scripts')
    @if ($browserKey)
        <script>
            window.initMapStyleDemo = () => {
                const status = document.querySelector('#map-style-status');
                const colorButtons = document.querySelectorAll('.map-color');
                const searchForm = document.querySelector('#area-search-form');
                const searchInput = document.querySelector('#area-search');
                const searchButton = searchForm.querySelector('button');
                const coloredAreasList = document.querySelector('#colored-areas-list');
                const map = new google.maps.Map(document.querySelector('#map-style-map'), {
                    center: { lat: 37.9433, lng: 23.6467 },
                    zoom: 13,
                    mapTypeControl: false,
                    streetViewControl: false,
                });
                let selectedColor = '#2878d0';
                let selectedFill = '#dbeafe';
                let lastBoundaryRequestAt = 0;
                const piraeusBoundary = [
                    { lat: 37.9367085, lng: 23.6209926 }, { lat: 37.9331237, lng: 23.6241321 },
                    { lat: 37.9330528, lng: 23.6250534 }, { lat: 37.933236, lng: 23.6255655 },
                    { lat: 37.9334329, lng: 23.6262321 }, { lat: 37.9335019, lng: 23.6270678 },
                    { lat: 37.9334797, lng: 23.6281391 }, { lat: 37.9326313, lng: 23.6276682 },
                    { lat: 37.9313697, lng: 23.6268705 }, { lat: 37.9300092, lng: 23.6273966 },
                    { lat: 37.9289233, lng: 23.6281962 }, { lat: 37.928238, lng: 23.629722 },
                    { lat: 37.9289579, lng: 23.6305045 }, { lat: 37.928116, lng: 23.6306491 },
                    { lat: 37.9267887, lng: 23.6310998 }, { lat: 37.9264706, lng: 23.6325631 },
                    { lat: 37.9265411, lng: 23.6347127 }, { lat: 37.9267615, lng: 23.6366294 },
                    { lat: 37.9277712, lng: 23.6382281 }, { lat: 37.9267036, lng: 23.6395287 },
                    { lat: 37.9273981, lng: 23.642253 }, { lat: 37.927842, lng: 23.6437568 },
                    { lat: 37.9296924, lng: 23.6458389 }, { lat: 37.9306271, lng: 23.6492338 },
                    { lat: 37.9322591, lng: 23.6528445 }, { lat: 37.931815, lng: 23.6469221 },
                    { lat: 37.9344473, lng: 23.6486848 }, { lat: 37.9359462, lng: 23.6480229 },
                    { lat: 37.9379586, lng: 23.6465295 }, { lat: 37.9361703, lng: 23.6503528 },
                    { lat: 37.934133, lng: 23.6498901 }, { lat: 37.9340468, lng: 23.6511772 },
                    { lat: 37.9348169, lng: 23.65205 }, { lat: 37.93554, lng: 23.6539684 },
                    { lat: 37.9353067, lng: 23.6583467 }, { lat: 37.9349709, lng: 23.6593039 },
                    { lat: 37.9361213, lng: 23.6609655 }, { lat: 37.9370952, lng: 23.6588011 },
                    { lat: 37.9382471, lng: 23.6615038 }, { lat: 37.9371179, lng: 23.6616996 },
                    { lat: 37.9384225, lng: 23.6633057 }, { lat: 37.9386198, lng: 23.6642261 },
                    { lat: 37.9393829, lng: 23.6630483 }, { lat: 37.9430135, lng: 23.6624451 },
                    { lat: 37.9402408, lng: 23.6626904 }, { lat: 37.939198, lng: 23.6641561 },
                    { lat: 37.9404321, lng: 23.6648861 }, { lat: 37.9408331, lng: 23.6676133 },
                    { lat: 37.9422007, lng: 23.6693332 }, { lat: 37.9395646, lng: 23.6697464 },
                    { lat: 37.9374359, lng: 23.6682127 }, { lat: 37.9399966, lng: 23.6705225 },
                    { lat: 37.9500893, lng: 23.6725227 }, { lat: 37.9568323, lng: 23.6684861 },
                    { lat: 37.958796, lng: 23.664076 }, { lat: 37.9596018, lng: 23.6604012 },
                    { lat: 37.962798, lng: 23.6577034 }, { lat: 37.964874, lng: 23.6524479 },
                    { lat: 37.9621738, lng: 23.6489809 }, { lat: 37.9632491, lng: 23.6376892 },
                    { lat: 37.9638713, lng: 23.628548 }, { lat: 37.9576523, lng: 23.6285779 },
                    { lat: 37.9513204, lng: 23.6287496 }, { lat: 37.9492733, lng: 23.6354549 },
                    { lat: 37.9452676, lng: 23.6340634 }, { lat: 37.9419107, lng: 23.6305608 },
                    { lat: 37.9471269, lng: 23.6369005 }, { lat: 37.9450859, lng: 23.6392426 },
                    { lat: 37.9403562, lng: 23.6395901 }, { lat: 37.9386713, lng: 23.6341211 },
                    { lat: 37.9363242, lng: 23.6215472 }, { lat: 37.9367085, lng: 23.6209926 },
                ];
                const coloredAreas = [];

                const fitColoredAreas = () => {
                    const bounds = new google.maps.LatLngBounds();

                    coloredAreas.forEach((area) => {
                        area.paths.flat(2).forEach((point) => bounds.extend(point));
                    });

                    if (! bounds.isEmpty()) {
                        map.fitBounds(bounds, 40);
                    }
                };

                const renderColoredAreas = () => {
                    coloredAreasList.replaceChildren(...coloredAreas.map((area) => {
                        const item = document.createElement('div');
                        const swatch = document.createElement('span');
                        const copy = document.createElement('div');
                        const name = document.createElement('strong');
                        const description = document.createElement('small');
                        const remove = document.createElement('button');

                        item.className = 'colored-area-item';
                        swatch.className = 'colored-area-swatch';
                        swatch.style.background = area.fillColor;
                        swatch.style.borderColor = area.strokeColor;
                        name.textContent = area.name;
                        description.textContent = area.description;
                        remove.type = 'button';
                        remove.dataset.areaId = area.id;
                        remove.setAttribute('aria-label', `Αφαίρεση περιοχής ${area.name}`);
                        remove.textContent = '×';
                        copy.append(name, description);
                        item.append(swatch, copy, remove);

                        return item;
                    }));
                };

                const addColoredArea = ({ id, name, description, paths }) => {
                    const existingIndex = coloredAreas.findIndex((area) => area.id === id);

                    if (existingIndex !== -1) {
                        coloredAreas[existingIndex].overlays.forEach((polygon) => polygon.setMap(null));
                        coloredAreas.splice(existingIndex, 1);
                    }

                    const area = {
                        id,
                        name,
                        description,
                        paths,
                        strokeColor: selectedColor,
                        fillColor: selectedFill,
                        overlays: paths.map((polygonPaths) => new google.maps.Polygon({
                            map,
                            paths: polygonPaths,
                            clickable: false,
                            fillColor: selectedFill,
                            fillOpacity: .48,
                            strokeColor: selectedColor,
                            strokeOpacity: 1,
                            strokeWeight: 3,
                        })),
                    };

                    coloredAreas.push(area);
                    renderColoredAreas();
                    fitColoredAreas();
                };

                addColoredArea({
                    id: 'piraeus-initial',
                    name: 'Πειραιάς',
                    description: 'Διοικητικό όριο Δήμου Πειραιώς',
                    paths: [[piraeusBoundary]],
                });

                colorButtons.forEach((button) => button.addEventListener('click', () => {
                    selectedColor = button.dataset.color;
                    selectedFill = button.dataset.fill;
                    colorButtons.forEach((candidate) => candidate.classList.toggle('is-active', candidate === button));
                }));

                coloredAreasList.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-area-id]');

                    if (! removeButton) {
                        return;
                    }

                    const areaIndex = coloredAreas.findIndex((area) => area.id === removeButton.dataset.areaId);

                    if (areaIndex === -1) {
                        return;
                    }

                    coloredAreas[areaIndex].overlays.forEach((polygon) => polygon.setMap(null));
                    coloredAreas.splice(areaIndex, 1);
                    renderColoredAreas();
                    fitColoredAreas();
                });

                searchForm.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const query = searchInput.value.trim();

                    if (! query) {
                        return;
                    }

                    searchButton.disabled = true;
                    status.classList.remove('is-error');
                    status.textContent = `Αναζήτηση για «${query}»…`;

                    try {
                        const waitTime = Math.max(0, 1000 - (Date.now() - lastBoundaryRequestAt));

                        if (waitTime > 0) {
                            await new Promise((resolve) => setTimeout(resolve, waitTime));
                        }

                        lastBoundaryRequestAt = Date.now();
                        const parameters = new URLSearchParams({
                            q: `${query}, Ελλάδα`,
                            format: 'geojson',
                            polygon_geojson: '1',
                            polygon_threshold: '0.0005',
                            addressdetails: '1',
                            countrycodes: 'gr',
                            limit: '5',
                            'accept-language': 'el',
                        });
                        const response = await fetch(`https://nominatim.openstreetmap.org/search?${parameters}`);

                        if (! response.ok) {
                            throw new Error('Boundary search failed.');
                        }

                        const collection = await response.json();
                        const feature = collection.features.find(({ geometry }) =>
                            ['Polygon', 'MultiPolygon'].includes(geometry?.type),
                        );

                        if (! feature) {
                            throw new Error('No polygon found.');
                        }

                        const coordinates = feature.geometry.type === 'Polygon'
                            ? [feature.geometry.coordinates]
                            : feature.geometry.coordinates;
                        const areaPolygons = coordinates.map((polygon) => polygon.map((ring) =>
                            ring.map(([lng, lat]) => ({ lat, lng })),
                        ));
                        const address = feature.properties.address ?? {};
                        const areaName = address.municipality
                            ?? address.city
                            ?? address.town
                            ?? address.village
                            ?? feature.properties.display_name.split(',')[0];

                        addColoredArea({
                            id: `${feature.properties.osm_type}-${feature.properties.osm_id}`,
                            name: areaName,
                            description: feature.properties.display_name,
                            paths: areaPolygons,
                        });
                        status.textContent = `Η περιοχή «${areaName}» προστέθηκε στον χάρτη.`;
                    } catch {
                        status.textContent = 'Δεν βρέθηκε διοικητικό όριο για αυτή την περιοχή.';
                        status.classList.add('is-error');
                    } finally {
                        searchButton.disabled = false;
                    }
                });

                status.textContent = 'Το όριο του Πειραιά εμφανίζεται με το επιλεγμένο χρώμα.';
            };
        </script>

        <script
            async
            src="https://maps.googleapis.com/maps/api/js?key={{ $browserKey }}&v=weekly&callback=initMapStyleDemo"
        ></script>
    @else
        <script>
            document.querySelector('#map-style-status').textContent = 'Προσθέστε το GOOGLE_MAPS_BROWSER_KEY για να φορτωθεί ο χάρτης.';
            document.querySelector('#map-style-status').classList.add('is-error');
        </script>
    @endif
@endpush
