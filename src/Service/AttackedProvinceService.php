<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\StackType;
use App\Enum\Type;
use App\Event\ProvinceStrengthComputedEvent;
use App\Exception\Rules\AttackedProvinceFacedownException;
use App\Exception\Rules\ProvinceNotAttackableException;
use App\Exception\Rules\WrongCardTypeException;
use App\State\Manager\GameStateManager;
use App\State\Model\ConflictState;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A province becoming an attacked province by an effect, outside the declaration of the conflict (MoveConflict,
 * AddAttackedProvince): an eligible province of the defending player, which a player reveals when it is face down
 * (glossary, Facedown Province). The stronghold restriction is not checked: the effect decides.
 */
readonly class AttackedProvinceService
{
    public function __construct(
        private ConflictService $conflictService,
        private GameStateManager $gameStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * An unbroken province of the defending player that the conflict is not at yet.
     */
    public function assertEligible(PhysicalCard $province, ConflictState $conflict): ProvinceCard
    {
        if (! $province instanceof ProvinceCard) {
            throw new WrongCardTypeException($province, Type::Province);
        }
        if ($province->getPlayer()->getId() !== $conflict->defendingPlayerId) {
            throw new ProvinceNotAttackableException($province, 'it does not belong to the defending player');
        }
        if ($province->getCurrentPlace() === ProvinceCard::STATE_BROKEN) {
            throw new ProvinceNotAttackableException($province, 'it is broken');
        }
        if (\in_array($province->getId(), $conflict->attackedProvinceIds, true)) {
            throw new ProvinceNotAttackableException($province, 'the conflict is already at it');
        }

        return $province;
    }

    /**
     * "If a facedown province becomes the attacked province in a manner other than the declaration of an attack,
     * immediately turn the province faceup": a player reveals it (RevealProvince), so that the players see it and may
     * react. Until then, nobody passes nor ends what they resolve. The step `reveal_attacked_province` is apart: it
     * waits for the revelation itself, and the interrupts to the declaration are resolved there (Know the Terrain).
     *
     * @throws AttackedProvinceFacedownException
     */
    public function assertRevealed(Game $game): void
    {
        $conflict = $this->gameStateManager->getState($game)->conflict;
        if ($conflict === null || $game->getCurrentPlace() === 'reveal_attacked_province') {
            return;
        }

        // through the province stacks of the defending player, which the view of the game loads with it
        $defender = $game->getPlayer($conflict->defendingPlayerId);
        foreach ([StackType::RegularProvince, StackType::StrongholdProvince] as $stackType) {
            foreach ($defender->getStacks($stackType) as $stack) {
                foreach ($stack->getCards() as $card) {
                    if ($card instanceof ProvinceCard && $card->getCurrentPlace() === ProvinceCard::STATE_FACEDOWN && \in_array($card->getId(), $conflict->attackedProvinceIds, true)) {
                        throw new AttackedProvinceFacedownException();
                    }
                }
            }
        }
    }

    /**
     * Once the strengths of the attacked provinces are recorded (determine_result), the new province gets its own.
     */
    public function recordStrength(Game $game, ConflictState $conflict, ProvinceCard $province): void
    {
        if ($conflict->provinceStrengths === []) {
            return;
        }

        $strength = $this->conflictService->printedStrength($province);
        $conflict->provinceStrengths[$province->getId()] = $strength;
        $this->eventDispatcher->dispatch(new ProvinceStrengthComputedEvent($game, $province, $strength));
    }
}
