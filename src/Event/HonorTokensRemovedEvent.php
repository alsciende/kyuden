<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\GameLog\GameLog;

/**
 * Honor tokens have been removed from a province or a holding, to the general token pool: by an effect, or because the
 * holding has left play.
 */
#[GameLog('Honor tokens are removed from {card}: {amount}')]
final readonly class HonorTokensRemovedEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public PhysicalCard $card,
        public int $amount,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
