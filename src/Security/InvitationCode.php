<?php

declare(strict_types=1);

namespace App\Security;

/**
 * The code of an invitation for a friend: 9 characters of the base 32 of Crockford (45 bits: no I, L, O nor U, which
 * are mistaken for other characters), shown in groups of three (`KX7-QM4-T9P`) so that it can be read aloud. Stored
 * without its dashes; what a friend types is normalized (case, dashes and spaces ignored).
 */
final class InvitationCode
{
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int LENGTH = 9;

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $code .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/', '', $code));
    }

    public static function format(string $code): string
    {
        return implode('-', str_split($code, 3));
    }
}
