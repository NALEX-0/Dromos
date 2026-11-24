<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Models\StopNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendStopSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public StopNotification $notification)
    {
        $this->onQueue('sms');
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(SmsGateway $smsGateway): void
    {
        $notification = $this->notification->fresh();

        if (! $notification || in_array($notification->status, ['accepted', 'delivered'], true)) {
            return;
        }

        $notification->update([
            'status' => 'sending',
            'failure_reason' => null,
        ]);

        try {
            $result = $smsGateway->send($notification->phone, $notification->message);

            $notification->update([
                'provider_message_id' => $result->messageId,
                'status' => $result->status,
                'sent_at' => now(),
                'provider_response' => $result->raw,
            ]);
        } catch (Throwable $exception) {
            $notification->update([
                'status' => 'retrying',
                'failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->notification->update([
            'status' => 'failed',
            'failure_reason' => $exception?->getMessage() ?? 'Η εργασία αποστολής SMS απέτυχε.',
        ]);
    }
}
