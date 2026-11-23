<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Phase;
use App\Message\CancelImminentTrigger;
use App\Message\RemoveFromGame;
use App\Model\InterruptOffer;
use App\Trigger\PhaseBeginningTrigger;

/**
 * Trading on the Sand Road.
 */
final readonly class TradingOnTheSandRoad17 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '17-trading-on-the-sand-road';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PhaseBeginningTrigger::class => 'onPhaseBeginning'];
    }

    /**
     * When the draw phase would begin: skip the draw phase.
     *
     * @return list<InterruptOffer>
     */
    public function onPhaseBeginning(PhaseBeginningTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->phase === Phase::Draw
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class, RemoveFromGame::class];
    }
}
