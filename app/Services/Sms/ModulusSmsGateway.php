<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Data\SmsResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ModulusSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): SmsResult
    {
        $configuration = config('services.modulus');
        $token = ($configuration['access_key'] ?? '').'$_$'.($configuration['secret_key'] ?? '');

        if (blank($configuration['access_key']) || blank($configuration['secret_key']) || blank($configuration['sender_id'])) {
            throw new RuntimeException('Η σύνδεση SMS με τη Modulus δεν έχει ρυθμιστεί πλήρως.');
        }

        $payload = [
            'recipients' => [['msisdn' => $phone]],
            'sms' => [
                'sender' => $configuration['sender_id'],
                'text' => $message,
                'encoding' => 'ucs2',
            ],
        ];

        if (filled($configuration['routing'])) {
            $payload['routing'] = $configuration['routing'];
        }

        if (filled($configuration['dlr_token'])) {
            $payload['dlr'] = true;
            $payload['dlrUrl'] = route('webhooks.modulus.dlr', [
                'token' => $configuration['dlr_token'],
            ]);
        }

        $response = Http::asJson()
            ->withToken($token)
            ->timeout(15)
            ->retry(2, 250)
            ->post(rtrim($configuration['base_url'], '/').'/ott-api/message', $payload)
            ->throw()
            ->json();

        $result = $response['messages'][0] ?? null;

        if (! is_array($result) || blank($result['messageId'] ?? null)) {
            throw new RuntimeException($result['message'] ?? 'Η Modulus δεν επέστρεψε αναγνωριστικό μηνύματος.');
        }

        return new SmsResult((string) $result['messageId'], 'accepted', $response);
    }
}
