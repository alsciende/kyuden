<?php

declare(strict_types=1);

namespace App\Service\CardScript;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Enum\PersonalHonor;
use App\Enum\StackType;
use App\Enum\Type;
use App\Service\ControlService;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\ConflictState;

/**
 * The conditions the triggered abilities of the cards often share, for their scripts (AbstractCardScript), which read
 * the state as it is when called. A condition no helper covers reads the state directly.
 */
final readonly class TriggerConditions
{
    public function __construct(
        private ControlService $controlService,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private CardStateManager $cardStateManager,
    ) {
    }

    /**
     * The player controls the card ("a province you control").
     */
    public function isControlledBy(PhysicalCard $card, Player $player): bool
    {
        return $this->controlService->getController($card) === $player;
    }

    /**
     * The player controlling the card ("you", in its text).
     */
    public function getController(PhysicalCard $card): Player
    {
        return $this->controlService->getController($card);
    }

    /**
     * The opponent of the player ("your opponent": two players in the Stronghold format).
     */
    public function getOpponent(Player $player): Player
    {
        foreach ($player->getGame()->getPlayers() as $other) {
            if ($other !== $player) {
                return $other;
            }
        }

        throw new \LogicException(\sprintf('The player %s has no opponent.', $player->getId()));
    }

    /**
     * The card has the element ("an [element-air] province").
     */
    public function hasElement(PhysicalCard $card, Element $element): bool
    {
        return in_array($element, $card->getLogicalCard()->getElements() ?? [], true);
    }

    /**
     * The card has the printed trait ("a Bushi character"), as the fixtures name it (`bushi`, `kaiu-wall`).
     */
    public function hasTrait(PhysicalCard $card, string $trait): bool
    {
        return in_array($trait, $card->getLogicalCard()->getTraits() ?? [], true);
    }

    /**
     * The role of the player is of the element ("a ring that matches the element of your role": Keeper of Air has the
     * trait `air`).
     */
    public function isElementOfRole(Player $player, Element $element): bool
    {
        foreach ($player->getUniqueStack(StackType::Role)->getCards() as $role) {
            if ($this->hasTrait($role, $element->value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The character is honored.
     */
    public function isHonored(PhysicalCard $character): bool
    {
        return $this->cardStateManager->getState($character)->getPersonalHonor() === PersonalHonor::Honored;
    }

    /**
     * The player has more honor than the other ("if you are more honorable than your opponent").
     */
    public function isMoreHonorable(Player $player, Player $than): bool
    {
        return ($this->playerStateManager->getState($player)->getHonor() ?? 0)
            > ($this->playerStateManager->getState($than)->getHonor() ?? 0);
    }

    /**
     * The number of cards in the hand of the player.
     */
    public function countCardsInHand(Player $player): int
    {
        return $player->getUniqueStack(StackType::Hand)->getCards()->count();
    }

    /**
     * The player is the first player.
     */
    public function isFirstPlayer(Player $player): bool
    {
        return $this->gameStateManager->getState($player->getGame())->firstPlayerId === $player->getId();
    }

    /**
     * A conflict is being resolved, from its declaration until its participants return home.
     */
    public function isConflictInProgress(Game $game): bool
    {
        return $this->getConflict($game) instanceof ConflictState;
    }

    /**
     * The conflict being resolved is of the type.
     */
    public function isConflictOfType(Game $game, ConflictType $type): bool
    {
        return $this->getConflict($game)?->type === $type;
    }

    /**
     * The contested ring of the conflict being resolved is of the element ("an [element-air] conflict").
     */
    public function isConflictOfElement(Game $game, Element $element): bool
    {
        return $this->getConflict($game)?->ring === $element;
    }

    /**
     * The character participates in the conflict being resolved, on either side.
     */
    public function isParticipating(PhysicalCard $character): bool
    {
        $conflict = $this->getConflict($character->getGame());

        return $conflict instanceof ConflictState
            && in_array($character->getId(), [...$conflict->attackerIds, ...$conflict->defenderIds], true);
    }

    /**
     * The character attacks in the conflict being resolved.
     */
    public function isAttacking(PhysicalCard $character): bool
    {
        $conflict = $this->getConflict($character->getGame());

        return $conflict instanceof ConflictState && in_array($character->getId(), $conflict->attackerIds, true);
    }

    /**
     * The character defends in the conflict being resolved.
     */
    public function isDefending(PhysicalCard $character): bool
    {
        $conflict = $this->getConflict($character->getGame());

        return $conflict instanceof ConflictState && in_array($character->getId(), $conflict->defenderIds, true);
    }

    /**
     * The player is the attacking player of the conflict being resolved.
     */
    public function isAttackingPlayer(Player $player): bool
    {
        return $this->getConflict($player->getGame())?->attackingPlayerId === $player->getId();
    }

    /**
     * The player is the defending player of the conflict being resolved.
     */
    public function isDefendingPlayer(Player $player): bool
    {
        return $this->getConflict($player->getGame())?->defendingPlayerId === $player->getId();
    }

    /**
     * The number of characters participating on the side of the player in the conflict being resolved.
     */
    public function countParticipants(Player $player): int
    {
        $conflict = $this->getConflict($player->getGame());

        return match (true) {
            ! $conflict instanceof ConflictState => 0,
            $conflict->attackingPlayerId === $player->getId() => count($conflict->attackerIds),
            $conflict->defendingPlayerId === $player->getId() => count($conflict->defenderIds),
            default => 0,
        };
    }

    /**
     * The characters participating on the side of the player in the conflict being resolved.
     *
     * @return list<PhysicalCard>
     */
    public function getParticipants(Player $player): array
    {
        return array_values(array_filter($player->getCharactersInPlay(), $this->isParticipating(...)));
    }

    /**
     * The province the card is attached to, or lies on (an attachment on a province: Untainted, Total Warfare).
     */
    public function getProvinceOf(PhysicalCard $card): ?ProvinceCard
    {
        foreach ($card->getStack()->getCards() as $other) {
            if ($other instanceof ProvinceCard) {
                return $other;
            }
        }

        return null;
    }

    /**
     * The province is attacked in the conflict being resolved (one of the provinces it is at).
     */
    public function isAttacked(PhysicalCard $province): bool
    {
        $conflict = $this->getConflict($province->getGame());

        return $conflict instanceof ConflictState && in_array($province->getId(), $conflict->attackedProvinceIds, true);
    }

    /**
     * The conflict being resolved is against the stronghold province (the province of the contested ring).
     */
    public function isAgainstStrongholdProvince(Game $game): bool
    {
        $conflict = $this->getConflict($game);
        if (! $conflict instanceof ConflictState) {
            return false;
        }

        foreach ($game->getPlayer($conflict->defendingPlayerId)->getUniqueStack(StackType::StrongholdProvince)->getCards() as $card) {
            if ($card instanceof ProvinceCard && $card->getId() === $conflict->provinceId) {
                return true;
            }
        }

        return false;
    }

    /**
     * A holding (with the trait, when given) is face up in the province ("a province that does not have a holding in
     * it", "a Kaiu Wall holding").
     */
    public function hasHolding(PhysicalCard $province, ?string $trait = null): bool
    {
        foreach ($province->getStack()->getCards() as $card) {
            if ($card instanceof DynastyCard
                && $card->getOrientation() === Orientation::FaceUp
                && $card->getLogicalCard()->getType() === Type::Holding
                && ($trait === null || $this->hasTrait($card, $trait))) {
                return true;
            }
        }

        return false;
    }

    /**
     * A holding with the trait is face up in one of the attacked provinces ("if the attacked province has a Kaiu Wall
     * holding").
     */
    public function isHoldingInAttackedProvince(Game $game, string $trait): bool
    {
        $conflict = $this->getConflict($game);
        if (! $conflict instanceof ConflictState) {
            return false;
        }

        foreach ($game->getPlayer($conflict->defendingPlayerId)->getCardsByClass(ProvinceCard::class) as $province) {
            if (in_array($province->getId(), $conflict->attackedProvinceIds, true) && $this->hasHolding($province, $trait)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Characters have been evaded by covert in the conflict being resolved.
     */
    public function hasEvadedCharacters(Game $game): bool
    {
        return ($this->getConflict($game)->evadedIds ?? []) !== [];
    }

    /**
     * The number of conflicts the player has declared this round, the one being declared included.
     */
    public function countDeclaredConflicts(Player $player): int
    {
        return count($this->playerStateManager->getState($player)->getDeclaredConflictTypes());
    }

    /**
     * The player has won the conflict being resolved, once its result is determined.
     */
    public function hasWonConflict(Player $player): bool
    {
        $winnerId = $this->getConflict($player->getGame())?->winnerId;

        return $winnerId !== null && $winnerId === $player->getId();
    }

    /**
     * The player has lost the conflict being resolved: the other player won it (nobody loses a conflict nobody won).
     */
    public function hasLostConflict(Player $player): bool
    {
        $conflict = $this->getConflict($player->getGame());

        return $conflict instanceof ConflictState
            && $conflict->winnerId !== null
            && $conflict->winnerId !== $player->getId()
            && in_array($player->getId(), [$conflict->attackingPlayerId, $conflict->defendingPlayerId], true);
    }

    /**
     * The character has won the conflict being resolved: it participates on the side of the winner ("this character
     * wins a conflict").
     */
    public function hasCharacterWonConflict(PhysicalCard $character): bool
    {
        return $this->isParticipating($character) && $this->hasWonConflict($this->getController($character));
    }

    /**
     * The character has lost the conflict being resolved: it participates on the side of the loser.
     */
    public function hasCharacterLostConflict(PhysicalCard $character): bool
    {
        return $this->isParticipating($character) && $this->hasLostConflict($this->getController($character));
    }

    /**
     * The conflict being resolved is unopposed: the attacking player won it, the defending player has no defending
     * character (3.2.4).
     */
    public function isUnopposed(Game $game): bool
    {
        $conflict = $this->getConflict($game);

        return $conflict instanceof ConflictState && $conflict->attackerWon() && $conflict->defenderIds === [];
    }

    /**
     * By how much skill the winner won the conflict being resolved ("by 5 or more skill"); 0 when nobody won it.
     */
    public function getWinningMargin(Game $game): int
    {
        $conflict = $this->getConflict($game);

        return match (true) {
            ! $conflict instanceof ConflictState || $conflict->winnerId === null => 0,
            default => abs($conflict->excessSkill()),
        };
    }

    private function getConflict(Game $game): ?ConflictState
    {
        return $this->gameStateManager->getState($game)->conflict;
    }
}
