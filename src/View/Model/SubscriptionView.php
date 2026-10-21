<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * The subscription of the authenticated user to the Mercure hub (`GET /api/self/subscription`).
 */
final readonly class SubscriptionView
{
    public function __construct(
        /** the public URL of the hub */
        public string $hub,
        /** the subscriber token, sent as the `authorization` query parameter of the subscription */
        public string $token,
        /** the topic of the user (`game_started`); a game is followed on its own topic (`/games/{id}`) */
        public string $topic,
        /** when the token expires (ISO 8601) */
        public string $expiresAt,
    ) {
    }
}
