<?php

namespace Tests\Feature;

use App\Contracts\Geocoder;
use App\Contracts\SmsGateway;
use App\Data\GeocodedAddress;
use App\Jobs\SendStopSms;
use App\Models\ColoredRoute;
use App\Models\RoutePlan;
use App\Models\User;
use App\Services\CachedGeocoder;
use App\Services\GoogleMapsRouteUrlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
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
            ->assertSee('class="route-create-page"', false)
            ->assertSee('Dromos')
            ->assertSee('Οι διαδρομές μου')
            ->assertSee('Σειριακή διαδρομή')
            ->assertSee('Υπολογισμός σειριακής διαδρομής')
            ->assertSee('href="'.route('ordered-route-plans.create').'" class="is-active"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Θράκης 11, 13671, Αχαρνές')
            ->assertSee('Εισαγωγή διευθύνσεων')
            ->assertSee('id="import-addresses"', false)
            ->assertDontSee('name="start[postal_code]"', false)
            ->assertDontSee('name="start[city]"', false);
    }

    public function test_optimized_planner_remains_available_from_its_tab(): void
    {
        $this->get(route('optimized-route-plans.create'))
            ->assertOk()
            ->assertSee('Σχεδιασμός διαδρομής')
            ->assertSee('Βελτιστοποίηση διαδρομής')
            ->assertSee('href="'.route('optimized-route-plans.create').'" class="is-active"', false);
    }

    public function test_colored_routes_page_is_available_to_admins(): void
    {
        auth()->user()->update(['role' => 'admin']);
        config(['services.google.browser_key' => 'test-browser-key']);

        $this->get(route('colored-routes.index'))
            ->assertOk()
            ->assertSee('Χρώματα διαδρομών')
            ->assertSee('id="colored-route-form"', false)
            ->assertSee('id="overlay-route-name"', false)
            ->assertSee('id="overlay-route-addresses"', false)
            ->assertSee('id="custom-route-color"', false)
            ->assertSee('new google.maps.Polyline', false)
            ->assertSee('new google.maps.Polygon', false)
            ->assertSee('fillColor: route.color', false)
            ->assertSee('data-color="#FF21DE"', false)
            ->assertSee('decodePath', false)
            ->assertSee('fullscreenControl: false', false);
    }

    public function test_user_can_create_toggle_edit_and_delete_a_colored_route(): void
    {
        auth()->user()->update(['role' => 'admin']);
        $this->post(route('colored-routes.store'), [
            'name' => 'Πρωινή διαδρομή',
            'color' => '#2878d0',
            'addresses' => "Θράκης 11, 13671, Αχαρνές\nΣύνταγμα, 10563, Αθήνα\nΘράκης 11, 13671, Αχαρνές",
        ])->assertRedirect();

        $coloredRoute = ColoredRoute::firstOrFail();
        $this->assertTrue($coloredRoute->is_enabled);
        $this->assertTrue($coloredRoute->is_circular);
        $this->assertSame(auth()->id(), $coloredRoute->user_id);

        $this->patch(route('colored-routes.toggle', $coloredRoute))->assertRedirect();
        $this->assertFalse($coloredRoute->fresh()->is_enabled);

        $this->patch(route('colored-routes.update', $coloredRoute), [
            'name' => 'Απογευματινή διαδρομή',
            'color' => '#FF21DE',
            'addresses' => "Θράκης 11, 13671, Αχαρνές\nΣύνταγμα, 10563, Αθήνα",
        ])->assertRedirect();
        $this->assertSame('Απογευματινή διαδρομή', $coloredRoute->fresh()->name);

        $this->delete(route('colored-routes.destroy', $coloredRoute))->assertRedirect();
        $this->assertDatabaseMissing('colored_routes', ['id' => $coloredRoute->id]);
    }

    public function test_enabled_colored_routes_are_supplied_to_both_route_creation_maps(): void
    {
        config(['services.google.browser_key' => 'test-browser-key']);
        $admin = User::factory()->create(['role' => 'admin']);
        $enabled = ColoredRoute::create([
            'user_id' => $admin->id,
            'name' => 'Ενεργή ζώνη',
            'color' => '#E6392F',
            'addresses' => ['Α', 'Β'],
            'path' => [['lat' => 37.98, 'lng' => 23.72], ['lat' => 38.01, 'lng' => 23.75]],
            'is_enabled' => true,
        ]);
        $disabled = ColoredRoute::create([
            'user_id' => $admin->id,
            'name' => 'Ανενεργή ζώνη',
            'color' => '#2878D0',
            'addresses' => ['Α', 'Β'],
            'path' => [['lat' => 37.90, 'lng' => 23.60], ['lat' => 37.91, 'lng' => 23.61]],
            'is_enabled' => false,
        ]);

        foreach ([route('ordered-route-plans.create'), route('optimized-route-plans.create')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('window.dromosColoredRoutes', false)
                ->assertSee('"id":'.$enabled->id, false)
                ->assertDontSee('"id":'.$disabled->id, false);
        }
    }

    public function test_normal_users_cannot_access_administration(): void
    {
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.routes'))->assertForbidden();
        $this->get(route('admin.sms'))->assertForbidden();
        $this->get(route('admin.api-usage'))->assertForbidden();
        $this->get(route('colored-routes.index'))->assertForbidden();
        $this->post(route('colored-routes.store'), [])->assertForbidden();
    }

    public function test_admin_can_view_and_edit_every_users_routes(): void
    {
        $admin = auth()->user();
        $admin->update(['role' => 'admin']);
        $owner = User::factory()->create();
        $plan = RoutePlan::create([
            'user_id' => $owner->id,
            'name' => 'Διαδρομή πελάτη',
            'status' => 'ready',
        ]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Πίνακας διαχείρισης')
            ->assertSee('class="admin-session"', false)
            ->assertSee('admin-account-badge', false);

        $this->get(route('admin.routes'))
            ->assertOk()
            ->assertSee('Διαδρομή πελάτη')
            ->assertSee($owner->email);

        $this->get(route('route-plans.index'))
            ->assertOk()
            ->assertSee('Όλες οι διαδρομές')
            ->assertSee('Διαδρομή πελάτη')
            ->assertSee($owner->email);

        $this->patch(route('route-plans.update', $plan), ['name' => 'Ενημερωμένη διαδρομή'])
            ->assertRedirect();

        $this->assertSame('Ενημερωμένη διαδρομή', $plan->fresh()->name);
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
            ->assertSee('route-history-document', false)
            ->assertSee('route-history-page', false)
            ->assertSee('Οι διαδρομές μου')
            ->assertSee('Πρωινές παραδόσεις')
            ->assertSee('Syntagma, Athens')
            ->assertDontSee('Ξένη διαδρομή');
    }

    public function test_route_history_uses_the_dromos_paginator(): void
    {
        foreach (range(1, 13) as $number) {
            RoutePlan::create([
                'user_id' => auth()->id(),
                'name' => 'Διαδρομή '.$number,
                'status' => 'ready',
            ]);
        }

        $this->get(route('route-plans.index'))
            ->assertOk()
            ->assertSee('class="dromos-pagination"', false)
            ->assertSee('Εμφάνιση 1–12')
            ->assertSee('Επόμενη ›');
    }

    public function test_a_route_can_be_renamed_from_its_workspace(): void
    {
        $plan = $this->createRouteWithTwoDestinations();

        $this->patch(route('route-plans.update', $plan), [
            'name' => 'Παραδόσεις Πειραιά',
        ])->assertRedirect();

        $this->assertSame('Παραδόσεις Πειραιά', $plan->fresh()->name);
        $this->get(route('route-plans.show', $plan))
            ->assertOk()
            ->assertSee('class="route-mode-title"', false)
            ->assertSee('Βελτιστοποιημένη διαδρομή');
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
        $coloredRoute = ColoredRoute::create([
            'user_id' => auth()->id(),
            'name' => 'Διαδρομή αναφοράς',
            'color' => '#E6392F',
            'addresses' => ['Α', 'Β'],
            'path' => [['lat' => 37.98, 'lng' => 23.72], ['lat' => 38.01, 'lng' => 23.75]],
            'is_enabled' => true,
        ]);

        $this->get(route('route-plans.show', $plan))
            ->assertOk()
            ->assertSee('"id":'.$coloredRoute->id, false)
            ->assertSee('Όνομα διαδρομής')
            ->assertSee('Αποφυγή διοδίων')
            ->assertSee('Άνοιγμα στο Google Maps')
            ->assertSee('Κοινοποίηση διαδρομής Dromos')
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

    public function test_a_stop_phone_is_normalized_and_saved(): void
    {
        $response = $this->post('/routes', [
            'start' => ['address' => 'Syntagma, Athens'],
            'stops' => [
                ['address' => 'Acropolis, Athens', 'phone' => '6912345678'],
                ['address' => 'Monastiraki, Athens', 'phone' => ''],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('stops', [
            'address' => 'Acropolis, Athens',
            'phone' => '306912345678',
        ]);
        $this->assertDatabaseHas('stops', [
            'address' => 'Monastiraki, Athens',
            'phone' => null,
        ]);
    }

    public function test_a_driver_can_queue_an_sms_notification_for_a_stop(): void
    {
        Queue::fake();
        config(['services.sms.driver' => 'fake']);
        $plan = $this->createRouteWithTwoDestinations();
        $stop = $plan->stops()->where('type', 'visit')->firstOrFail();
        $stop->update(['phone' => '306912345678']);

        $this->post(route('route-plans.stops.notifications.store', [$plan, $stop]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('stop_notifications', [
            'stop_id' => $stop->id,
            'user_id' => auth()->id(),
            'provider' => 'fake',
            'phone' => '306912345678',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SendStopSms::class);
    }

    public function test_a_stop_without_a_phone_cannot_be_notified(): void
    {
        $plan = $this->createRouteWithTwoDestinations();
        $stop = $plan->stops()->where('type', 'visit')->firstOrFail();

        $this->post(route('route-plans.stops.notifications.store', [$plan, $stop]))
            ->assertRedirect()
            ->assertSessionHasErrors('notification');

        $this->assertDatabaseCount('stop_notifications', 0);
    }

    public function test_a_shared_route_is_public_read_only_and_can_send_notifications(): void
    {
        Queue::fake();
        $plan = $this->createRouteWithTwoDestinations()->fresh();
        $stop = $plan->stops()->where('type', 'visit')->firstOrFail();
        $stop->update(['phone' => '306912345678']);
        $shareUrl = route('route-plans.shared', $plan->share_token);

        auth()->logout();

        $this->get($shareUrl)
            ->assertOk()
            ->assertSee('Κοινόχρηστη διαδρομή')
            ->assertSee('Ειδοποίηση SMS')
            ->assertSee('Syntagma, Athens')
            ->assertSee('Acropolis, Athens')
            ->assertSee('Monastiraki, Athens')
            ->assertDontSee('class="app-sidebar"', false)
            ->assertDontSee('306912345678')
            ->assertDontSee('Άνοιγμα στο Google Maps')
            ->assertDontSee('Σειριακή διαδρομή · χωρίς βελτιστοποίηση σειράς')
            ->assertDontSee('Επεξεργασία')
            ->assertDontSee('Διαγραφή')
            ->assertDontSee('Επαναϋπολογισμός διαδρομής');

        $this->post(route('route-plans.shared.stops.notifications.store', [$plan->share_token, $stop]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('stop_notifications', [
            'stop_id' => $stop->id,
            'phone' => '306912345678',
            'status' => 'queued',
        ]);

        Queue::assertPushed(SendStopSms::class);

        $this->get($shareUrl)
            ->assertOk()
            ->assertSee('class="is-sent"', false);

        $this->get(route('route-plans.shared', 'invalid-token'))->assertNotFound();
    }

    public function test_the_sms_job_processes_a_queued_notification(): void
    {
        Queue::fake();
        $plan = $this->createRouteWithTwoDestinations();
        $stop = $plan->stops()->where('type', 'visit')->firstOrFail();
        $stop->update(['phone' => '306912345678']);

        $this->post(route('route-plans.stops.notifications.store', [$plan, $stop]));
        $notification = $stop->notifications()->firstOrFail();

        (new SendStopSms($notification))->handle(app(SmsGateway::class));

        $this->assertDatabaseHas('stop_notifications', [
            'id' => $notification->id,
            'status' => 'accepted',
        ]);
        $this->assertNotNull($notification->fresh()->provider_message_id);
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
            ->assertSee('class="route-mode-title"', false)
            ->assertSee('Σειριακή διαδρομή')
            ->assertDontSee('χωρίς βελτιστοποίηση σειράς')
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
