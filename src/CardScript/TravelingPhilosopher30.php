<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\TurnProvinceFacedown;
use App\Model\InterruptOffer;
use App\Trigger\CardLeavingPlayTrigger;

/**
 * Traveling Philosopher.
 */
final readonly class TravelingPhilosopher30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-traveling-philosopher';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [TurnProvinceFacedown::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeavingPlayTrigger::class => 'onCardLeavingPlay'];
    }

    /**
     * When this character leaves play.
     *
     * @return list<InterruptOffer>
     */
    public function onCardLeavingPlay(CardLeavingPlayTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->card === $card
            ? [new InterruptOffer($card)]
            : [];
    }
}
