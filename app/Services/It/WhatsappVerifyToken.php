<?php

namespace App\Services\It;

final class WhatsappVerifyToken
{
    public const MIN_LENGTH = 32;

    public const MAX_LENGTH = 255;

    /**
     * Values previously distributed by the project or commonly copied from examples.
     *
     * @var list<string>
     */
    private const UNSAFE_VALUES = [
        'siges-webhook-token',
        'change-me',
        'changeme',
        'default',
        'secret',
        'token',
        'verify-token',
        'webhook-token',
        'your-verify-token',
    ];

    public static function isSecure(mixed $token): bool
    {
        if (! is_string($token)
            || strlen($token) < self::MIN_LENGTH
            || strlen($token) > self::MAX_LENGTH
            || $token !== trim($token)) {
            return false;
        }

        if (in_array(strtolower($token), self::UNSAFE_VALUES, true)) {
            return false;
        }

        if (count(array_unique(str_split($token))) < 8) {
            return false;
        }

        return preg_match('/^(.{1,16})\1+$/', $token) !== 1;
    }
}
