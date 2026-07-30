<?php

namespace App\Support;

final class PhoneNumber
{
    /**
     * Normalize a Ghana phone number to the international 233XXXXXXXXX form.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim($value));

        if (!is_string($digits)) {
            return null;
        }

        if (preg_match('/^0[0-9]{9}$/', $digits)) {
            return '233'.substr($digits, 1);
        }

        if (preg_match('/^233[0-9]{9}$/', $digits)) {
            return $digits;
        }

        return null;
    }
}
