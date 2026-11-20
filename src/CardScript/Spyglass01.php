<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CharacterMovedToConflictEvent;
use App\Event\ConflictDeclarationCompletedEvent;
use App\Event\DefendersDeclaredEvent;
use App\Model\ReactionOffer;

/**
 * Spyglass.
 */
final readonly class Spyglass01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-spyglass';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
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
     * After attached character moves to a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onCharacterMovedToConflict(CharacterMovedToConflictEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && $event->card === $character
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After attached character commits to a conflict: declared as an attacker.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictDeclarationCompleted(ConflictDeclarationCompletedEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && in_array($character, $event->attackers, true)
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After attached character commits to a conflict: declared as a defender.
     *
     * @return list<ReactionOffer>
     */
    public function onDefendersDeclared(DefendersDeclaredEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && in_array($character, $event->defenders, true)
            ? [new ReactionOffer($card)]
            : [];
    }
}
