<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A provider of identities (OAuth2) an account is linked to, to sign in with it.
 */
enum OAuthProvider: string
{
    case Discord = 'discord';
    case Google = 'google';
}
