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
 * Asako Azunami.
 */
final readonly class AsakoAzunami12 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '12-asako-azunami';
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
     * When you would resolve the water ring: instead of the normal ring effect.
     *
     * @return list<InterruptOffer>
     */
    public function onRingEffectResolving(RingEffectResolvingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->ring === Element::Water && $trigger->player === $this->triggerConditions->getController($card)
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
