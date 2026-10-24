<?php

declare(strict_types=1);

namespace App\Service\Bot;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Message\BidHonor;
use App\Message\ChooseStrongholdProvince;
use App\Message\ClaimImperialFavor;
use App\Message\CollectFate;
use App\Message\DeclareConflictSkill;
use App\Message\DeclareGloryCount;
use App\Message\DeclareNoDefenders;
use App\Message\PassConflict;
use App\Message\PassOpportunity;
use App\Message\PlayerActionInterface;
use App\Message\RevealDynastyCard;
use App\Service\AvailableActionsResolver;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;

/**
 * Chooses the next action of a bot from the actions available to it (AvailableActionsResolver), so that it never
 * sends what the engine would refuse in the current step.
 *
 * First version: the bot only does what the framework steps require (choosing its stronghold province, revealing its
 * facedown dynasty cards, collecting fate, the declarations of totals), with the printed values of its cards, and
 * passes every opportunity. It plays no card, declares no conflict and no defender; it bids 2 honor. It does not
 * resolve duels beyond the honor bid.
 */
final readonly class BotStrategy
{
    public const int HONOR_BID = 2;

    public function __construct(
        private AvailableActionsResolver $availableActionsResolver,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
    ) {
    }

    /**
     * @return PlayerActionInterface|null the next message of the player, null when it has nothing to do now
     */
    public function nextAction(Game $game, Player $player): ?PlayerActionInterface
    {
        $available = $this->availableActionsResolver->resolve($game, $player);
        $gameId = $game->getId();
        $playerId = $player->getId();
        $playerState = $this->playerStateManager->getState($player);

        if (in_array('choose_stronghold_province', $available, true) && $playerState->getStrongholdProvinceId() === null) {
            return new ChooseStrongholdProvince($gameId, $playerId, $this->chooseStrongholdProvince($player)->getId());
        }

        // RevealDynastyCard is accepted anytime: the step tells when it is due
        if ($game->getCurrentPlace() === 'reveal_facedown_dynasty_cards') {
            $card = $this->findFacedownDynastyCard($player);
            if ($card instanceof PhysicalCard) {
                return new RevealDynastyCard($gameId, $playerId, $card->getId());
            }
        }

        if (in_array('collect_fate', $available, true)) {
            return new CollectFate($gameId, $playerId, $player->getStronghold()->getLogicalCard()->getFate() ?? 0);
        }

        if (in_array('bid_honor', $available, true)) {
            return new BidHonor($gameId, $playerId, self::HONOR_BID);
        }

        if (in_array('declare_no_defenders', $available, true)) {
            return new DeclareNoDefenders($gameId, $playerId);
        }

        if (in_array('pass_conflict', $available, true)) {
            return new PassConflict($gameId, $playerId);
        }

        if (in_array('declare_conflict_skill', $available, true)) {
            return new DeclareConflictSkill($gameId, $playerId, $this->conflictSkill($game, $player));
        }

        if (in_array('declare_glory_count', $available, true)) {
            return new DeclareGloryCount($gameId, $playerId, $this->gloryCount($player));
        }

        if (in_array('claim_imperial_favor', $available, true)) {
            return new ClaimImperialFavor($gameId, $playerId, ConflictType::Military);
        }

        if (in_array('pass_opportunity', $available, true)) {
            return new PassOpportunity($gameId, $playerId);
        }

        return null;
    }

    private function chooseStrongholdProvince(Player $player): PhysicalCard
    {
        $provinces = $player->getCardsByClass(ProvinceCard::class)->getValues();
        usort($provinces, fn (PhysicalCard $a, PhysicalCard $b): int => $a->getLogicalCard()->getId() <=> $b->getLogicalCard()->getId());

        return $provinces[0] ?? throw new \LogicException("The player {$player->getId()} has no province.");
    }

    /**
     * A face-down dynasty card in a regular province of the player (1.1).
     */
    private function findFacedownDynastyCard(Player $player): ?PhysicalCard
    {
        foreach ($player->getStacks(StackType::RegularProvince) as $province) {
            foreach ($province->getCards() as $card) {
                if ($card->getCurrentPlace() === DynastyCard::STATE_PROVINCE_FACEDOWN) {
                    return $card;
                }
            }
        }

        return null;
    }

    /**
     * The printed skill of the ready characters of the player in the conflict.
     */
    private function conflictSkill(Game $game, Player $player): int
    {
        $conflict = $this->gameStateManager->getState($game)->currentConflict();
        $participantIds = $conflict->attackingPlayerId === $player->getId() ? $conflict->attackerIds : $conflict->defenderIds;
        $skill = 0;
        foreach ($player->getCharactersInPlay() as $character) {
            if (in_array($character->getId(), $participantIds, true) && $character->getOrientation() !== Orientation::Sideways) {
                $logicalCard = $character->getLogicalCard();
                $skill += (int) ($conflict->type === ConflictType::Military ? $logicalCard->getMilitary() : $logicalCard->getPolitical());
            }
        }

        return $skill;
    }

    /**
     * The printed glory of the ready characters of the player.
     */
    private function gloryCount(Player $player): int
    {
        $glory = 0;
        foreach ($player->getCharactersInPlay() as $character) {
            if ($character->getOrientation() !== Orientation::Sideways) {
                $glory += $character->getLogicalCard()->getGlory() ?? 0;
            }
        }

        return $glory;
    }
}
