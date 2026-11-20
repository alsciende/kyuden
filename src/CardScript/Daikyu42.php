<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\ConflictType;
use App\Event\CharacterMovedToConflictEvent;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Event\DefendersDeclaredEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Daikyū.
 */
final readonly class Daikyu42 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '42-daikyu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function isAbilityGrantedToAttached(): bool
    {
        return true;
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            CharacterMovedToConflictEvent::class => 'onCharacterMovedToConflict',
            ConflictDeclarationCompletedEvent::class => 'onConflictDeclarationCompleted',
            DefendersDeclaredEvent::class => 'onDefendersDeclared',
        ];
    }

    /**
     * After a character moves to a [conflict-military] conflict attached character is participating in (granted to it).
     *
     * @return list<ReactionOffer>
     */
    public function onCharacterMovedToConflict(CharacterMovedToConflictEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && $event->card !== $character
            && $this->triggerConditions->isConflictOfType($game, ConflictType::Military)
            && $this->triggerConditions->isParticipating($character)
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After a character commits to a [conflict-military] conflict attached character is participating in: declared as an attacker (granted to it).
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && $this->triggerConditions->isConflictOfType($game, ConflictType::Military)
            && $this->triggerConditions->isParticipating($character)
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After a character commits to a [conflict-military] conflict attached character is participating in: declared as a defender (granted to it).
     *
     * @return list<ReactionOffer>
     */
    public function onDefendersDeclared(DefendersDeclaredEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && $event->defenders !== []
            && $this->triggerConditions->isConflictOfType($game, ConflictType::Military)
            && $this->triggerConditions->isParticipating($character)
            ? [new ReactionOffer($card)]
            : [];
    }
}
