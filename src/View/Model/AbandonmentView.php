<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * Whether the player may end a game their opponent no longer plays (`GET /api/games/{id}/abandonment`, see
 * AbandonmentClaimer).
 */
final readonly class AbandonmentView
{
    public function __construct(
        /** @var list<string> the players the game waits for, in player order */
        public array $awaitedPlayerIds,
        /** from when the player may end the game if it does not change; null when it does not wait for their opponent */
        public ?\DateTimeImmutable $claimableAt,
        /** the player may end the game now */
        public bool $claimable,
    ) {
    }
}
