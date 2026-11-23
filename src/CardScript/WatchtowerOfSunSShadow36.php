<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Model\InterruptOffer;
use App\Trigger\ProvinceBreaking;

/**
 * Watchtower of Sun's Shadow.
 */
final readonly class WatchtowerOfSunSShadow36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-watchtower-of-sun-s-shadow';
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
     * When this holding's province is broken (forced).
     *
     * @return list<InterruptOffer>
     */
    public function onProvinceBreaking(ProvinceBreaking $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->province->getStack() === $card->getStack()
            ? [new InterruptOffer($card, forced: true)]
            : [];
    }
}
