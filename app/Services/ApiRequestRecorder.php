<?php

namespace App\Services;

use App\Models\ApiRequestLog;
use Throwable;

final class ApiRequestRecorder
{
    public function record(
        string $service,
        string $operation,
        string $status,
        int $durationMs,
        ?int $httpStatus = null,
        bool $cacheHit = false,
        ?Throwable $error = null,
        array $metadata = [],
    ): void {
        try {
            ApiRequestLog::create([
                'user_id' => auth()->id(),
                'provider' => 'google',
                'service' => $service,
                'operation' => $operation,
                'status' => $status,
                'http_status' => $httpStatus,
                'duration_ms' => max(0, $durationMs),
                'cache_hit' => $cacheHit,
                'error_type' => $error ? class_basename($error) : null,
                'error_message' => $error ? mb_substr($error->getMessage(), 0, 1000) : null,
                'metadata' => $metadata ?: null,
                'requested_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public static function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
