<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\OAuthProvider;

/**
 * An identity at a provider linked to the account of the authenticated user: they sign in with it.
 */
final readonly class LinkedAccountView
{
    public function __construct(
        public OAuthProvider $provider,
        public \DateTimeImmutable $linkedAt,
    ) {
    }
}
