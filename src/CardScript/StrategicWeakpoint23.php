<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Model\InterruptOffer;
use App\Trigger\ProvinceBreaking;

/**
 * Strategic Weakpoint.
 */
final readonly class StrategicWeakpoint23 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '23-strategic-weakpoint';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ProvinceBreaking::class => 'onProvinceBreaking'];
    }

    /**
     * When the holding's province would be broken.
     *
     * @return list<InterruptOffer>
     */
    public function onProvinceBreaking(ProvinceBreaking $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->province->getStack() === $card->getStack()
            ? [new InterruptOffer($card, would: true)]
            : [];
    }
}
