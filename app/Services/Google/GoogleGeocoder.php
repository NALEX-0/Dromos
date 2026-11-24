<?php

namespace App\Services\Google;

use App\Contracts\Geocoder;
use App\Data\GeocodedAddress;
use App\Services\ApiRequestRecorder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

final readonly class GoogleGeocoder implements Geocoder
{
    public function __construct(
        private Factory $http,
        private ApiRequestRecorder $requestRecorder,
    ) {}

    public function geocode(string $address): GeocodedAddress
    {
        $startedAt = hrtime(true);
        $response = null;

        try {
            $response = $this->http->get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'region' => 'gr',
                'language' => 'el',
                'key' => config('services.google.server_key'),
            ])->throw();
            $result = $response->json('results.0');

            if (! $result) {
                throw new RuntimeException("Google could not locate: {$address}");
            }

            $this->recordRequest($startedAt, 'success', $response);

            return new GeocodedAddress($result['formatted_address'], $result['geometry']['location']['lat'], $result['geometry']['location']['lng'], $result['place_id'] ?? null);
        } catch (Throwable $exception) {
            $this->recordRequest($startedAt, 'failed', $response, $exception);

            throw $exception;
        }
    }

    private function recordRequest(int $startedAt, string $status, ?Response $response = null, ?Throwable $error = null): void
    {
        $this->requestRecorder->record(
            service: 'geocoding',
            operation: 'geocode',
            status: $status,
            durationMs: ApiRequestRecorder::elapsedMilliseconds($startedAt),
            httpStatus: $response?->status(),
            error: $error,
        );
    }
}
