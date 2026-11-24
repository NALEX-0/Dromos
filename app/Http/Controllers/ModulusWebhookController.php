<?php

namespace App\Http\Controllers;

use App\Models\StopNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ModulusWebhookController extends Controller
{
    public function deliveryReport(Request $request, string $token): JsonResponse
    {
        $configuredToken = (string) config('services.modulus.dlr_token');

        abort_if($configuredToken === '' || ! hash_equals($configuredToken, $token), 404);

        $payload = $request->validate([
            'messageId' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:1000'],
            'sentAt' => ['nullable'],
            'deliveryReportAt' => ['nullable'],
        ]);

        $notification = StopNotification::where('provider_message_id', $payload['messageId'])->first();

        if (! $notification) {
            return response()->json(['received' => true], 202);
        }

        $status = $this->statusFrom($payload);

        if ($notification->status === 'delivered') {
            $status = 'delivered';
        }

        $providerResponse = $notification->provider_response ?? [];
        $providerResponse['delivery_report'] = $request->all();

        $updates = [
            'status' => $status,
            'provider_response' => $providerResponse,
        ];

        if ($status === 'delivered') {
            $updates['delivered_at'] = $this->reportedAt($payload['deliveryReportAt'] ?? null);
            $updates['failure_reason'] = null;
        } elseif ($status === 'failed') {
            $updates['failure_reason'] = $payload['reason'] ?? $payload['message'] ?? 'Delivery failed.';
        }

        $notification->update($updates);

        return response()->json(['received' => true]);
    }

    private function statusFrom(array $payload): string
    {
        $code = isset($payload['code']) ? (int) $payload['code'] : null;

        if (in_array($code, [1, 15], true)) {
            return 'delivered';
        }

        if (in_array($code, [2, 7, 8, 11, 12], true)) {
            return 'failed';
        }

        $description = mb_strtolower(($payload['reason'] ?? '').' '.($payload['message'] ?? ''));

        if (str_contains($description, 'deliver') || str_contains($description, 'παραδό')) {
            return 'delivered';
        }

        if (str_contains($description, 'fail') || str_contains($description, 'reject') || str_contains($description, 'invalid')) {
            return 'failed';
        }

        return 'accepted';
    }

    private function reportedAt(mixed $value): Carbon
    {
        try {
            return filled($value) ? Carbon::parse($value) : now();
        } catch (Throwable) {
            return now();
        }
    }
}
