<?php

namespace App\Data;

final readonly class SmsResult
{
    public function __construct(
        public string $messageId,
        public string $status,
        public array $raw = [],
    ) {}
}
