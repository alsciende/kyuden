<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Trophy;
use App\State\Model\GameState;

/**
 * Finish a game against the house bot, won or lost, played to its end.
 */
readonly class HouseBotGame implements TrophyInterface
{
    #[\Override]
    public static function getTrophy(): Trophy
    {
        return Trophy::HouseBotGame;
    }

    #[\Override]
    public function isEarnedBy(Player $player, Game $game, GameState $state): bool
    {
        return $game->isTraining() && $state->victoryCondition?->isPlayedToTheEnd() === true;
    }
}
