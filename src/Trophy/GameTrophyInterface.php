<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Entity\Game;
use App\Entity\Player;
use App\State\Model\GameState;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The rule of a trophy earned in a game: read by Service/Trophy/GameTrophies once a game has ended, for each human
 * player, never in a replay.
 */
#[AutoconfigureTag]
interface GameTrophyInterface extends TrophyInterface
{
    /**
     * Whether the player earns the trophy in the game, which has ended.
     *
     * @param GameState $state the state of the game, read once for every trophy
     */
    public function isEarnedBy(Player $player, Game $game, GameState $state): bool;
}
