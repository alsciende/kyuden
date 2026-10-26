<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Why a human is sent to a provider of identities: to sign in to the account linked to their identity, or to link it
 * to the account they are signed in to.
 */
enum OAuthPurpose: string
{
    case SignIn = 'sign_in';
    case Link = 'link';
}
