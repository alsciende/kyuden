<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Trophy;
use App\State\Model\GameState;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The rule of a trophy earned in a game, one class per trophy, named after its case of Enum/Trophy (which identifies
 * it, gives its reward and its place in the catalogue). Read by Service/Trophy/GameTrophies once a game has ended,
 * for each human player, never in a replay; a stateless service, which reads only what is stored, so that the games
 * played before earn the trophy too (`app:trophies:award`).
 */
#[AutoconfigureTag]
interface TrophyInterface
{
    /**
     * The trophy this rule awards.
     */
    public static function getTrophy(): Trophy;

    /**
     * Whether the player earns the trophy in the game, which has ended.
     *
     * @param GameState $state the state of the game, read once for every trophy
     */
    public function isEarnedBy(Player $player, Game $game, GameState $state): bool;
}
