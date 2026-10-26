<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * Where the web client sends the human to authorize the application at a provider of identities, and the state the
 * provider sends back, which the web client keeps to check it.
 */
final readonly class OAuthAuthorizationView
{
    public function __construct(
        public string $authorizationUrl,
        public string $state,
    ) {
    }
}
