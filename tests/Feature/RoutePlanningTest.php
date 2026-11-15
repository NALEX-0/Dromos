<?php

namespace Tests\Feature;

use App\Contracts\Geocoder;
use App\Data\GeocodedAddress;
use App\Models\RoutePlan;
use App\Models\User;
use App\Services\CachedGeocoder;
use App\Services\GoogleMapsRouteUrlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoutePlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_planner_page_is_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Dromos')
            ->assertSee('Οι διαδρομές μου')
            ->assertSee('Βελτιστοποίηση διαδρομής')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Θράκης 11, 13671, Αχαρνές')
            ->assertSee('Εισαγωγή διευθύνσεων')
            ->assertSee('id="import-addresses"', false)
            ->assertDontSee('name="start[postal_code]"', false)
            ->assertDontSee('name="start[city]"', false);
    }

    public function test_map_style_demo_page_is_available(): void
    {
        config([
            'services.google.browser_key' => 'test-browser-key',
        ]);

        $this->get(route('map-style-demo'))
            ->assertOk()
            ->assertSee('Χρωματισμός περιοχών')
            ->assertSee('id="area-search-form"', false)
            ->assertSee('id="colored-areas-list"', false)
            ->assertSee('id="map-style-map"', false)
            ->assertSee('new google.maps.Polygon', false)
            ->assertSee('addColoredArea', false)
            ->assertSee('nominatim.openstreetmap.org/search', false)
            ->assertSee('OpenStreetMap contributors')
            ->assertSee('app-navigation-link is-active', false);
    }

    public function test_route_history_lists_only_the_authenticated_users_routes(): void
    {
        $plan = $this->createRouteWithTwoDestinations();
        $plan->update(['name' => 'Πρωινές παραδόσεις']);
        $otherUser = User::factory()->create();
        RoutePlan::create([
            'user_id' => $otherUser->id,
            'name' => 'Ξένη διαδρομή',
            'status' => 'ready',
        ]);

        $this->get(route('route-plans.index'))
            ->assertOk()
            ->assertSee('Οι διαδρομές μου')
            ->assertSee('Πρωινές παραδόσεις')
            ->assertSee('Syntagma, Athens')
            ->assertDontSee('Ξένη διαδρομή');
    }

    public function test_a_route_can_be_renamed_from_its_workspace(): void
    {
        $plan = $this->createRouteWithTwoDestinations();

        $this->patch(route('route-plans.update', $plan), [
            'name' => 'Παραδόσεις Πειραιά',
        ])->assertRedirect();

        $this->assertSame('Παραδόσεις Πειραιά', $plan->fresh()->name);
    }

    public function test_a_route_can_be_deleted_from_history(): void
    {
        $plan = $this->createRouteWithTwoDestinations();

        $this->delete(route('route-plans.destroy', $plan))
            ->assertRedirect(route('route-plans.index'));

        $this->assertDatabaseMissing('route_plans', ['id' => $plan->id]);
        $this->assertDatabaseMissing('stops', ['route_plan_id' => $plan->id]);
    }

    public function test_a_route_can_be_geocoded_optimized_and_saved(): void
    {
        $response = $this->post('/routes', [
            'avoid_tolls' => true,
            'start' => ['address' => 'Syntagma Square, 105 63, Athens'],
            'stops' => [
                ['address' => 'Acropolis Museum, 117 42, Athens'],
                ['address' => 'Monastiraki Square, 105 55, Athens'],
            ],
        ]);
        $plan = RoutePlan::first();
        $response->assertRedirect(route('route-plans.show', $plan));
        $this->assertDatabaseCount('route_plans', 1);
        $this->assertFalse(Schema::hasTable('vehicles'));
        $this->assertDatabaseCount('stops', 3);
        $this->assertDatabaseHas('stops', ['address' => 'Acropolis Museum, 117 42, Athens']);
        $this->assertSame('ready', $plan->status);
        $this->assertNotNull($plan->share_token);
        $this->assertSame(auth()->id(), $plan->user_id);
        $this->assertTrue($plan->avoid_tolls);
        $this->assertTrue($plan->provider_payload['avoid_tolls']);

        config(['services.google.browser_key' => 'test-browser-key']);

        $this->get(route('route-plans.show', $plan))
            ->assertOk()
            ->assertSee('Όνομα διαδρομής')
            ->assertSee('Αποφυγή διοδίων')
            ->assertSee('id="route-options-form"', false)
            ->assertSeeInOrder([
                '＋ Προσθήκη στάσης',
                'id="route-options-form"',
                'Επαναϋπολογισμός διαδρομής',
            ], false)
            ->assertSee('draggable="true"', false)
            ->assertSee('data-stop-id=', false)
            ->assertSee('Σύρετε για αλλαγή σειράς')
            ->assertSee('submitUpdatedOrder')
            ->assertSee('dromos.map-view.', false)
            ->assertSee('persistDromosMapView', false)
            ->assertSee('Άνοιγμα στο Google Maps')
            ->assertSee('Αντιγραφή συνδέσμου')
            ->assertSee('Κοινοποίηση διαδρομής Dromos')
            ->assertSee(route('route-plans.shared', $plan->share_token), false)
            ->assertSee('Κοινοποίηση')
            ->assertSee('https://www.google.com/maps/search/?api=1', false)
            ->assertSee('query=Acropolis%20Museum%2C%20117%2042%2C%20Athens', false)
            ->assertSee('class="share-stop"', false)
            ->assertDontSee('Εξαγωγή διευθύνσεων')
            ->assertDontSee('id="export-addresses"', false);

        $plan->update(['total_duration_seconds' => 3900]);
        $this->get(route('route-plans.show', $plan))->assertOk()->assertSee('1 ώρα 5 λεπτά');
    }

    public function test_a_shared_route_requires_login_and_is_read_only(): void
    {
        $plan = $this->createRouteWithTwoDestinations()->fresh();
        $shareUrl = route('route-plans.shared', $plan->share_token);

        auth()->logout();

        $this->get($shareUrl)->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());

        $this->get($shareUrl)
            ->assertOk()
            ->assertSee('Κοινόχρηστη διαδρομή')
            ->assertSee('Μόνο για προβολή')
            ->assertSee('Syntagma, Athens')
            ->assertSee('Acropolis, Athens')
            ->assertSee('Monastiraki, Athens')
            ->assertSee('Άνοιγμα στο Google Maps')
            ->assertDontSee('Επεξεργασία')
            ->assertDontSee('Διαγραφή')
            ->assertDontSee('Επαναϋπολογισμός διαδρομής');

        $this->get(route('route-plans.shared', 'invalid-token'))->assertNotFound();
    }

    public function test_stops_can_be_manually_forced_into_an_exact_order(): void
    {
        $this->post('/routes', [
            'avoid_tolls' => true,
            'start' => ['address' => 'Syntagma', 'city' => 'Athens'],
            'stops' => [
                ['address' => 'Acropolis', 'city' => 'Athens'],
                ['address' => 'Monastiraki', 'city' => 'Athens'],
                ['address' => 'Kallithea', 'city' => 'Athens'],
            ],
        ]);
        $plan = RoutePlan::first();
        $ids = $plan->stops()->where('type', 'visit')->orderBy('optimized_order')->pluck('id')->reverse()->values()->all();

        $submittedIds = array_map(fn ($id) => (string) $id, $ids);

        $this->patch(route('route-plans.reorder', $plan), ['stop_ids' => $submittedIds])->assertRedirect();

        $this->assertSame($ids, $plan->stops()->where('type', 'visit')->orderBy('optimized_order')->pluck('id')->all());
        $this->assertTrue($plan->fresh()->provider_payload['avoid_tolls']);
    }

    public function test_a_destination_can_be_updated_and_recalculated(): void
    {
        $plan = $this->createRouteWithThreeDestinations();
        $stop = $plan->stops()->where('type', 'visit')->firstOrFail();

        $this->patch(route('route-plans.stops.update', [$plan, $stop]), [
            'address' => 'Updated address, 104 31, Athens',
        ])->assertRedirect(route('route-plans.show', $plan));

        $this->assertSame('Updated address, 104 31, Athens', $stop->fresh()->address);
        $this->assertNotNull($plan->fresh()->total_duration_seconds);
    }

    public function test_a_destination_can_be_added_to_an_existing_route(): void
    {
        $plan = $this->createRouteWithTwoDestinations();

        $this->post(route('route-plans.stops.store', $plan), [
            'address' => 'Kallithea, 176 76, Athens',
        ])->assertRedirect(route('route-plans.show', $plan));

        $this->assertDatabaseHas('stops', [
            'route_plan_id' => $plan->id,
            'address' => 'Kallithea, 176 76, Athens',
            'type' => 'visit',
        ]);
        $this->assertSame(3, $plan->stops()->where('type', 'visit')->count());
    }

    public function test_a_destination_can_be_deleted_and_recalculated(): void
    {
        $plan = $this->createRouteWithThreeDestinations();
        $stop = $plan->stops()->where('type', 'visit')->firstOrFail();

        $this->delete(route('route-plans.stops.destroy', [$plan, $stop]))
            ->assertRedirect(route('route-plans.show', $plan));

        $this->assertDatabaseMissing('stops', ['id' => $stop->id]);
        $this->assertSame(2, $plan->stops()->where('type', 'visit')->count());
    }

    public function test_a_route_cannot_have_fewer_than_two_destinations(): void
    {
        $plan = $this->createRouteWithTwoDestinations();
        $stop = $plan->stops()->where('type', 'visit')->firstOrFail();

        $this->delete(route('route-plans.stops.destroy', [$plan, $stop]))->assertSessionHasErrors('route');
        $this->assertDatabaseHas('stops', ['id' => $stop->id]);
    }

    public function test_google_maps_links_are_split_and_preserve_route_options(): void
    {
        $stops = collect(range(1, 12))->map(fn ($number) => ['address' => "Stop {$number}, Athens"])->all();
        $this->post('/routes', [
            'avoid_tolls' => true,
            'return_to_start' => true,
            'start' => ['address' => 'Start, Athens'],
            'stops' => $stops,
        ]);
        $plan = RoutePlan::firstOrFail()->load('stops');
        $links = app(GoogleMapsRouteUrlBuilder::class)->build($plan);

        $this->assertCount(2, $links);
        $this->assertTrue($plan->return_to_start);
        $this->assertStringContainsString('avoid=tolls', $links[0]);
        $this->assertStringNotContainsString('dir_action=navigate', $links[0]);

        parse_str(parse_url($links[0], PHP_URL_QUERY), $firstLinkParameters);

        $start = $plan->stops->firstWhere('type', 'start');
        $this->assertSame($start->formatted_address, $firstLinkParameters['origin']);
        $this->assertSame($start->place_id, $firstLinkParameters['origin_place_id']);
        $this->assertArrayHasKey('waypoint_place_ids', $firstLinkParameters);

        foreach ($links as $link) {
            parse_str(parse_url($link, PHP_URL_QUERY), $parameters);

            $this->assertDoesNotMatchRegularExpression('/^-?\d+(?:\.\d+)?,-?\d+(?:\.\d+)?$/', $parameters['origin']);
            $this->assertDoesNotMatchRegularExpression('/^-?\d+(?:\.\d+)?,-?\d+(?:\.\d+)?$/', $parameters['destination']);
        }

        $this->get(route('route-plans.show', $plan))
            ->assertSee('Αντιγραφή τμήματος 1')
            ->assertSee('Αντιγραφή τμήματος 2');
    }

    public function test_geocoding_results_are_reused_for_equivalent_addresses(): void
    {
        $provider = new class implements Geocoder
        {
            public int $calls = 0;

            public function geocode(string $address): GeocodedAddress
            {
                $this->calls++;

                return new GeocodedAddress('Θράκης 11, Αχαρνές', 38.083, 23.733, 'cached-place');
            }
        };
        $geocoder = new CachedGeocoder($provider);

        $first = $geocoder->geocode(' Θράκης 11, 13671, ΑΧΑΡΝΈΣ ');
        $second = $geocoder->geocode('θράκης 11,13671,αχαρνές');

        $this->assertSame(1, $provider->calls);
        $this->assertSame($first->placeId, $second->placeId);
        $this->assertDatabaseCount('geocoded_address_cache', 1);
        $this->assertDatabaseHas('geocoded_address_cache', ['hit_count' => 1]);
    }

    public function test_avoid_tolls_is_disabled_by_default(): void
    {
        $this->post('/routes', [
            'start' => ['address' => 'Syntagma', 'city' => 'Athens'],
            'stops' => [
                ['address' => 'Acropolis', 'city' => 'Athens'],
                ['address' => 'Monastiraki', 'city' => 'Athens'],
            ],
        ]);

        $this->assertFalse(RoutePlan::firstOrFail()->avoid_tolls);
    }

    public function test_toll_avoidance_can_be_changed_on_an_existing_route(): void
    {
        $plan = $this->createRouteWithTwoDestinations();

        $this->patch(route('route-plans.options.update', $plan), [
            'avoid_tolls' => true,
        ])->assertRedirect(route('route-plans.show', $plan));

        $plan->refresh();

        $this->assertTrue($plan->avoid_tolls);
        $this->assertTrue($plan->provider_payload['avoid_tolls']);
        $this->get(route('route-plans.show', $plan))
            ->assertOk()
            ->assertSee('id="route-options-form"', false)
            ->assertSee('name="avoid_tolls"', false)
            ->assertSee('checked', false);
    }

    public function test_changing_toll_avoidance_preserves_sequential_mode(): void
    {
        $this->post(route('ordered-route-plans.store'), [
            'start' => ['address' => 'Ordered Start, Athens'],
            'stops' => [
                ['address' => 'Ordered Stop 1, Athens'],
                ['address' => 'Ordered Stop 2, Athens'],
            ],
        ]);
        $plan = RoutePlan::firstOrFail();
        $orderBeforeRecalculation = $plan->stops()
            ->where('type', 'visit')
            ->orderBy('optimized_order')
            ->pluck('id')
            ->all();

        $this->patch(route('route-plans.options.update', $plan), [
            'avoid_tolls' => true,
        ])->assertRedirect(route('route-plans.show', $plan));

        $this->assertSame('ordered', $plan->fresh()->route_mode);
        $this->assertSame(
            $orderBeforeRecalculation,
            $plan->stops()->where('type', 'visit')->orderBy('optimized_order')->pluck('id')->all(),
        );
    }

    public function test_at_least_two_destinations_are_required(): void
    {
        $this->post('/routes', ['start' => ['address' => 'Syntagma', 'city' => 'Athens'], 'stops' => [['address' => 'Acropolis', 'city' => 'Athens']]])->assertSessionHasErrors('stops');
    }

    public function test_up_to_twenty_five_destinations_are_accepted(): void
    {
        $stops = collect(range(1, 25))
            ->map(fn ($number) => ['address' => "Stop {$number}", 'city' => 'Athens'])
            ->all();

        $this->post('/routes', [
            'start' => ['address' => 'Start', 'city' => 'Athens'],
            'stops' => $stops,
        ])->assertSessionDoesntHaveErrors('stops');

        $this->assertDatabaseCount('stops', 26);
    }

    public function test_more_than_twenty_five_destinations_are_rejected(): void
    {
        $stops = collect(range(1, 26))
            ->map(fn ($number) => ['address' => "Stop {$number}", 'city' => 'Athens'])
            ->all();

        $this->post('/routes', [
            'start' => ['address' => 'Start', 'city' => 'Athens'],
            'stops' => $stops,
        ])->assertSessionHasErrors('stops');
    }

    public function test_ordered_planner_accepts_more_than_twenty_five_stops_without_optimizing_them(): void
    {
        $this->get(route('ordered-route-plans.create'))
            ->assertOk()
            ->assertSee('Σειριακή διαδρομή')
            ->assertSee('Έως 100 στάσεις')
            ->assertSee('app-navigation-link is-active', false);

        $stops = collect(range(1, 35))
            ->map(fn ($number) => ['address' => "Ordered Stop {$number}, Athens"])
            ->all();

        $response = $this->post(route('ordered-route-plans.store'), [
            'start' => ['address' => 'Ordered Start, Athens'],
            'stops' => $stops,
        ]);
        $plan = RoutePlan::firstOrFail();

        $response->assertRedirect(route('route-plans.show', $plan));
        $this->assertSame('ordered', $plan->route_mode);
        $this->assertNotNull($plan->share_token);
        $this->assertSame(35, $plan->stops()->where('type', 'visit')->count());
        $this->assertSame(
            ['Ordered Stop 1, Athens', 'Ordered Stop 2, Athens', 'Ordered Stop 3, Athens'],
            $plan->stops()->where('type', 'visit')->orderBy('optimized_order')->limit(3)->pluck('address')->all(),
        );
        $this->assertCount(4, $plan->provider_payload['segments']);
        $this->get(route('route-plans.show', $plan))
            ->assertSee('χωρίς βελτιστοποίηση σειράς')
            ->assertSee('Κοινοποίηση διαδρομής Dromos');
    }

    public function test_ordered_planner_rejects_more_than_one_hundred_stops(): void
    {
        $stops = collect(range(1, 101))
            ->map(fn ($number) => ['address' => "Ordered Stop {$number}, Athens"])
            ->all();

        $this->post(route('ordered-route-plans.store'), [
            'start' => ['address' => 'Ordered Start, Athens'],
            'stops' => $stops,
        ])->assertSessionHasErrors('stops');
    }

    private function createRouteWithThreeDestinations(): RoutePlan
    {
        $this->post('/routes', [
            'start' => ['address' => 'Syntagma, Athens'],
            'stops' => [
                ['address' => 'Acropolis, Athens'],
                ['address' => 'Monastiraki, Athens'],
                ['address' => 'Kallithea, Athens'],
            ],
        ]);

        return RoutePlan::firstOrFail();
    }

    private function createRouteWithTwoDestinations(): RoutePlan
    {
        $this->post('/routes', [
            'start' => ['address' => 'Syntagma, Athens'],
            'stops' => [
                ['address' => 'Acropolis, Athens'],
                ['address' => 'Monastiraki, Athens'],
            ],
        ]);

        return RoutePlan::firstOrFail();
    }
}
