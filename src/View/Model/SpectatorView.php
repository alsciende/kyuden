<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * An account watching a game (`GET /api/games/{id}/spectators`).
 */
final readonly class SpectatorView
{
    public function __construct(
        public string $userId,
        /** their name at court */
        public string $name,
    ) {
    }
}
