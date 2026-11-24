<?php

namespace App\Actions;

use App\Jobs\SendStopSms;
use App\Models\Stop;
use App\Models\StopNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SendStopNotification
{
    public function execute(Stop $stop, User $user): StopNotification
    {
        return DB::transaction(function () use ($stop, $user) {
            Stop::query()->whereKey($stop->id)->lockForUpdate()->firstOrFail();

            $recentNotification = $stop->notifications()
                ->whereIn('status', ['queued', 'sending', 'retrying', 'accepted', 'delivered'])
                ->where('created_at', '>=', now()->subMinutes(5))
                ->latest()
                ->first();

            if ($recentNotification) {
                return $recentNotification;
            }

            $notification = $stop->notifications()->create([
                'user_id' => $user->id,
                'provider' => config('services.sms.driver'),
                'phone' => $stop->phone,
                'message' => (string) config('services.sms.message'),
                'status' => 'queued',
            ]);

            SendStopSms::dispatch($notification)->afterCommit();

            return $notification;
        });
    }
}
