<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CharacterMovedToConflictEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Moto Stables.
 */
final readonly class MotoStables30 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '30-moto-stables';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CharacterMovedToConflictEvent::class => 'onCharacterMovedToConflict'];
    }

    /**
     * After a character you control moves to a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onCharacterMovedToConflict(CharacterMovedToConflictEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $this->triggerConditions->isControlledBy($event->card, $you)
            ? [new ReactionOffer($card)]
            : [];
    }
}
