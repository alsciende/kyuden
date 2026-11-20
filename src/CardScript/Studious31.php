<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ConflictWonEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Studious.
 */
final readonly class Studious31 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '31-studious';
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
        return [ConflictWonEvent::class => 'onConflictWon'];
    }

    /**
     * After this character (attached character) wins a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onConflictWon(ConflictWonEvent $event, PhysicalCard $card, Game $game): array
    {
        $character = $card->getAttachedCharacter();

        return $character instanceof PhysicalCard
            && $this->triggerConditions->hasCharacterWonConflict($character)
            ? [new ReactionOffer($card)]
            : [];
    }
}
