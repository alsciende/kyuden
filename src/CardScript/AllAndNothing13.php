<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Element;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Service\CardScript\TriggerConditions;
use App\Trigger\RingEffectResolvingTrigger;

/**
 * All and Nothing.
 */
final readonly class AllAndNothing13 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '13-all-and-nothing';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [RingEffectResolvingTrigger::class => 'onRingEffectResolving'];
    }

    /**
     * When you would resolve the void ring effect: resolve another ring's effect instead.
     *
     * @return list<InterruptOffer>
     */
    public function onRingEffectResolving(RingEffectResolvingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->ring === Element::Void && $trigger->player === $this->triggerConditions->getController($card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
