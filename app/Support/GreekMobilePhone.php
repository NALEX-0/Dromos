<?php

namespace App\Support;

final class GreekMobilePhone
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0030')) {
            $digits = substr($digits, 2);
        }

        if (preg_match('/^69\d{8}$/', $digits)) {
            return '30'.$digits;
        }

        return preg_match('/^3069\d{8}$/', $digits) ? $digits : null;
    }
}
