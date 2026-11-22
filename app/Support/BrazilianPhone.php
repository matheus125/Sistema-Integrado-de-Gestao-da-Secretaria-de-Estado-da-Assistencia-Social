<?php

namespace App\Support;

final class BrazilianPhone
{
    /** @var list<string> */
    private const AREA_CODES = [
        '11', '12', '13', '14', '15', '16', '17', '18', '19',
        '21', '22', '24', '27', '28',
        '31', '32', '33', '34', '35', '37', '38',
        '41', '42', '43', '44', '45', '46', '47', '48', '49',
        '51', '53', '54', '55',
        '61', '62', '63', '64', '65', '66', '67', '68', '69',
        '71', '73', '74', '75', '77', '79',
        '81', '82', '83', '84', '85', '86', '87', '88', '89',
        '91', '92', '93', '94', '95', '96', '97', '98', '99',
    ];

    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\+?[0-9().\-\s]+$/u', $value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if (! is_string($digits)) {
            return null;
        }

        if (in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    public static function isValid(mixed $value): bool
    {
        $phone = self::normalize($value);

        if ($phone === null || ! in_array(strlen($phone), [10, 11], true)) {
            return false;
        }

        if (! in_array(substr($phone, 0, 2), self::AREA_CODES, true)) {
            return false;
        }

        $subscriber = substr($phone, 2);

        return strlen($subscriber) === 9
            ? str_starts_with($subscriber, '9')
            : preg_match('/^[2-5]/', $subscriber) === 1;
    }

    public static function toE164(mixed $value): ?string
    {
        $phone = self::normalize($value);

        return self::isValid($value) && $phone !== null ? '55'.$phone : null;
    }
}
