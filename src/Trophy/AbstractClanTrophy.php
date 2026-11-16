<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Entity\CardTypes\StrongholdCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Clan;
use App\Enum\Trophy;
use App\State\Model\GameState;

/**
 * Win a standard game (neither friendly nor against the house bot), played to its end, with a stronghold of the clan.
 */
abstract readonly class AbstractClanTrophy implements TrophyInterface
{
    /**
     * The Great Clan of the stronghold.
     */
    abstract public static function getClan(): Clan;

    #[\Override]
    public static function getTrophy(): Trophy
    {
        return Trophy::ofClan(static::getClan()) ?? throw new \LogicException('A trophy of a clan is for a Great Clan.');
    }

    #[\Override]
    public function isEarnedBy(Player $player, Game $game, GameState $state): bool
    {
        return $game->isStandard()
            && $state->victoryCondition?->isPlayedToTheEnd() === true
            && $player->getId() === $state->winnerId
            && $this->clanOf($player) === static::getClan();
    }

    /**
     * The clan of the stronghold of the player: none without one (Player::getStronghold() throws), so that the end of
     * a game never fails on a trophy.
     */
    private function clanOf(Player $player): ?Clan
    {
        $stronghold = $player->getCardsByClass(StrongholdCard::class)->first();

        return $stronghold === false ? null : $stronghold->getLogicalCard()->getClan();
    }
}
