<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Data\SmsResult;
use Illuminate\Support\Str;

final class FakeSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): SmsResult
    {
        return new SmsResult('fake-'.Str::uuid(), 'accepted', [
            'phone' => $phone,
            'message' => $message,
            'simulated' => true,
        ]);
    }
}
