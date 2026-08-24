<?php

namespace Tests\Feature;

use App\Models\RoutePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('ordered-route-plans.create'))->assertRedirect(route('login'));
    }

    public function test_a_guest_can_register(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Δημιουργία λογαριασμού');

        $this->post(route('register'), [
            'name' => 'Νίκος',
            'email' => 'nikos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('route-plans.create'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Νίκος',
            'email' => 'nikos@example.com',
        ]);
    }

    public function test_a_user_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('route-plans.create'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_invalid_login_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_user_cannot_view_or_modify_another_users_route(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($owner)->post('/routes', [
            'start' => ['address' => 'Syntagma, Athens'],
            'stops' => [
                ['address' => 'Acropolis, Athens'],
                ['address' => 'Monastiraki, Athens'],
            ],
        ]);
        $plan = RoutePlan::firstOrFail();
        $stopIds = $plan->stops()->where('type', 'visit')->pluck('id')->all();

        $this->actingAs($otherUser)->get(route('route-plans.show', $plan))->assertForbidden();
        $this->actingAs($otherUser)->patch(route('route-plans.reorder', $plan), ['stop_ids' => $stopIds])->assertForbidden();
    }
}
