<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\Clan;

/**
 * The last game a deck was played in, among those ended.
 */
final readonly class DeckLastGameView
{
    public function __construct(
        public string $gameId,
        /** when it ended (its last message) */
        public ?\DateTimeImmutable $endedAt,
        /** whether the user won it, null when nobody won */
        public ?bool $won,
        /** the clan of the stronghold of the opponent */
        public ?Clan $opponentClan,
    ) {
    }
}
