<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\RoutePlan;
use App\Models\StopNotification;
use App\Models\User;
use App\Services\Google\GoogleGeocoder;
use App\Services\Sms\ModulusSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiTelemetryAndModulusWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_geocoding_records_status_and_timing(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'results' => [[
                    'formatted_address' => 'Θράκης 11, Αχαρνές 136 71, Ελλάδα',
                    'geometry' => ['location' => ['lat' => 38.083, 'lng' => 23.735]],
                    'place_id' => 'test-place',
                ]],
                'status' => 'OK',
            ]),
        ]);

        app(GoogleGeocoder::class)->geocode('Θράκης 11, 13671, Αχαρνές');

        $log = ApiRequestLog::firstOrFail();
        $this->assertSame('geocoding', $log->service);
        $this->assertSame('success', $log->status);
        $this->assertSame(200, $log->http_status);
        $this->assertFalse($log->cache_hit);
        $this->assertGreaterThanOrEqual(0, $log->duration_ms);
    }

    public function test_modulus_delivery_webhook_updates_notification(): void
    {
        config(['services.modulus.dlr_token' => 'test-delivery-token']);
        $user = User::factory()->create();
        $route = RoutePlan::create(['user_id' => $user->id, 'name' => 'Test', 'status' => 'ready']);
        $stop = $route->stops()->create([
            'type' => 'visit',
            'input_order' => 0,
            'optimized_order' => 0,
            'address' => 'Test address',
            'geocoding_status' => 'resolved',
        ]);
        $notification = StopNotification::create([
            'stop_id' => $stop->id,
            'user_id' => $user->id,
            'provider' => 'modulus',
            'provider_message_id' => 'message-123',
            'phone' => '306912345678',
            'message' => 'Test message',
            'status' => 'accepted',
            'provider_response' => ['messages' => [['messageId' => 'message-123']]],
        ]);

        $this->postJson(route('webhooks.modulus.dlr', ['token' => 'test-delivery-token']), [
            'messageId' => 'message-123',
            'code' => 1,
            'reason' => 'DELIVERED',
            'deliveryReportAt' => '2026-11-16T12:00:00+02:00',
        ])->assertOk()->assertJson(['received' => true]);

        $notification->refresh();
        $this->assertSame('delivered', $notification->status);
        $this->assertNotNull($notification->delivered_at);
        $this->assertSame(1, $notification->provider_response['delivery_report']['code']);
    }

    public function test_modulus_delivery_webhook_rejects_an_invalid_token(): void
    {
        config(['services.modulus.dlr_token' => 'correct-token']);

        $this->postJson(route('webhooks.modulus.dlr', ['token' => 'wrong-token']), [
            'messageId' => 'message-123',
            'code' => 1,
        ])->assertNotFound();
    }

    public function test_modulus_gateway_requests_delivery_reports_when_configured(): void
    {
        config(['services.modulus' => [
            'base_url' => 'https://messaging.modulus.gr',
            'access_key' => 'access',
            'secret_key' => 'secret',
            'sender_id' => 'Dromos',
            'routing' => null,
            'dlr_token' => 'delivery-token',
        ]]);
        Http::fake(['messaging.modulus.gr/*' => Http::response([
            'messages' => [['messageId' => 'message-456']],
        ])]);

        app(ModulusSmsGateway::class)->send('306912345678', 'Test');

        Http::assertSent(fn ($request) => $request['dlr'] === true
            && str_contains($request['dlrUrl'], '/webhooks/modulus/delivery-reports/delivery-token'));
    }

    public function test_only_admins_can_view_google_api_usage(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.api-usage'))->assertForbidden();

        $user->update(['role' => 'admin']);
        ApiRequestLog::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'service' => 'routes',
            'operation' => 'compute_routes',
            'status' => 'success',
            'http_status' => 200,
            'duration_ms' => 145,
            'cache_hit' => false,
            'requested_at' => now(),
        ]);

        $this->actingAs($user)->get(route('admin.api-usage'))
            ->assertOk()
            ->assertSee('Google API')
            ->assertSee('145 ms');
    }
}
