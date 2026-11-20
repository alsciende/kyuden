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
 * Ide Trader.
 */
final readonly class IdeTrader01 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '01-ide-trader';
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
     * After 1 or more characters move to a conflict in which this character is participating.
     *
     * @return list<ReactionOffer>
     */
    public function onCharacterMovedToConflict(CharacterMovedToConflictEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->card !== $card
            && $this->triggerConditions->isParticipating($card)
            ? [new ReactionOffer($card)]
            : [];
    }
}
