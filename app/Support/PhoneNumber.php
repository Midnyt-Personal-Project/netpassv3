<?php

namespace App\Support;

final class PhoneNumber
{
    /**
     * Normalize a Ghana phone number to the local 0XXXXXXXXX form
     * (e.g. 0244123456). Both "0244123456" and "233244123456" become
     * "0244123456". This is the format stored in the database and shown
     * everywhere in the admin panel.
     */
    public static function normalize(?string $value): ?string
    {
        $digits = self::digits($value);

        if ($digits === null) {
            return null;
        }

        if (preg_match('/^0[2-9][0-9]{8}$/', $digits)) {
            return $digits;
        }

        if (preg_match('/^233[2-9][0-9]{8}$/', $digits)) {
            return '0'.substr($digits, 3);
        }

        return null;
    }

    /**
     * Convert a Ghana phone number to the international 233XXXXXXXXX form,
     * regardless of whether it is currently local or international.
     */
    public static function international(?string $value): ?string
    {
        $digits = self::digits($value);

        if ($digits === null) {
            return null;
        }

        if (preg_match('/^0[2-9][0-9]{8}$/', $digits)) {
            return '233'.substr($digits, 1);
        }

        if (preg_match('/^233[2-9][0-9]{8}$/', $digits)) {
            return $digits;
        }

        return null;
    }

    /**
     * Both valid spellings of a Ghana number, local first. Useful when
     * matching against rows that may predate the local-format standard.
     *
     * @return array<int, string>
     */
    public static function variants(?string $value): array
    {
        $local = self::normalize($value);
        $international = self::international($value);

        return array_values(array_unique(array_filter([$local, $international])));
    }

    /** Digits only, or null when the value is not a usable string. */
    private static function digits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim($value));

        return is_string($digits) && $digits !== '' ? $digits : null;
    }
}
