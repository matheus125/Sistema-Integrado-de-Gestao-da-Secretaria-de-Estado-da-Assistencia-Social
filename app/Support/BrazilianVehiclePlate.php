<?php

namespace App\Support;

final class BrazilianVehiclePlate
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        if ($value === '' || ! preg_match('/^[A-Z]{3}[\s-]?[0-9A-Z]{4}$/', $value)) {
            return null;
        }

        $compact = preg_replace('/[\s-]/', '', $value);

        if (! is_string($compact)) {
            return null;
        }

        if (preg_match('/^[A-Z]{3}[0-9]{4}$/', $compact)) {
            return substr($compact, 0, 3).'-'.substr($compact, 3);
        }

        if (preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $compact)) {
            return $compact;
        }

        return null;
    }

    public static function isValid(mixed $value): bool
    {
        return self::normalize($value) !== null;
    }
}
