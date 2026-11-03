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
        /** the topic of the user (`game_started`); a game is followed on its own topic (`/games/{id}`) */
        public string $topic,
        /** when the subscriber token, set in a cookie for the hub, expires (ISO 8601) */
        public string $expiresAt,
    ) {
    }
}
