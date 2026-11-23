<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\RingReturningTrigger;

/**
 * Wholeness of the World.
 */
final readonly class WholenessOfTheWorld08 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '08-wholeness-of-the-world';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [RingReturningTrigger::class => 'onRingReturning'];
    }

    /**
     * When a ring in your claimed ring pool would be returned to the unclaimed ring pool: it remains claimed instead (the ring named by the cancel).
     *
     * @return list<InterruptOffer>
     */
    public function onRingReturning(RingReturningTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->player === $this->triggerConditions->getController($card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
