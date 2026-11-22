<?php

namespace App\Support;

final class Renavam
{
    /** @var list<int> */
    private const WEIGHTS = [3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match('/^\d{11}$/D', $value) === 1 ? $value : null;
    }

    public static function isValid(mixed $value): bool
    {
        $renavam = self::normalize($value);

        if ($renavam === null) {
            return false;
        }

        $sum = 0;

        foreach (self::WEIGHTS as $index => $weight) {
            $sum += ((int) $renavam[$index]) * $weight;
        }

        $digit = 11 - ($sum % 11);

        if ($digit >= 10) {
            $digit = 0;
        }

        return $digit === (int) $renavam[10];
    }
}
