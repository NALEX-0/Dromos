<?php

namespace App\Contracts;

use App\Data\SmsResult;

interface SmsGateway
{
    public function send(string $phone, string $message): SmsResult;
}
