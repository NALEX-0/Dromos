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
            ->assertSee('Βελτιστοποίηση διαδρομής')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Θράκης 11, 13671, Αχαρνές')
            ->assertSee('Εισαγωγή διευθύνσεων')
            ->assertSee('id="import-addresses"', false)
            ->assertDontSee('name="start[postal_code]"', false)
            ->assertDontSee('name="start[city]"', false);
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
        $this->assertSame(auth()->id(), $plan->user_id);
        $this->assertTrue($plan->avoid_tolls);
        $this->assertTrue($plan->provider_payload['avoid_tolls']);
        $this->get(route('route-plans.show', $plan))
            ->assertOk()
            ->assertSee('Διαδρομές')
            ->assertSee('Αποφυγή διοδίων ενεργή')
            ->assertSee('draggable="true"', false)
            ->assertSee('data-stop-id=', false)
            ->assertSee('Σύρετε για αλλαγή σειράς')
            ->assertSee('submitUpdatedOrder')
            ->assertSee('Άνοιγμα στο Google Maps')
            ->assertSee('Αντιγραφή συνδέσμου');

        $plan->update(['total_duration_seconds' => 3900]);
        $this->get(route('route-plans.show', $plan))->assertOk()->assertSee('1 ώρα 5 λεπτά');
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
        $this->assertStringContainsString(urlencode((string) $plan->stops->firstWhere('type', 'start')->latitude), $links[1]);
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
            ->assertSee('planner-link is-active', false);

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
        $this->assertSame(35, $plan->stops()->where('type', 'visit')->count());
        $this->assertSame(
            ['Ordered Stop 1, Athens', 'Ordered Stop 2, Athens', 'Ordered Stop 3, Athens'],
            $plan->stops()->where('type', 'visit')->orderBy('optimized_order')->limit(3)->pluck('address')->all(),
        );
        $this->assertCount(4, $plan->provider_payload['segments']);
        $this->get(route('route-plans.show', $plan))->assertSee('χωρίς βελτιστοποίηση σειράς');
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
