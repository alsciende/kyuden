<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Model\InterruptOffer;
use App\Trigger\ProvinceBreaking;

/**
 * Cycle of Vengeance.
 */
final readonly class CycleOfVengeance37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-cycle-of-vengeance';
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
     * When this province is broken.
     *
     * @return list<InterruptOffer>
     */
    public function onProvinceBreaking(ProvinceBreaking $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->province === $card
            ? [new InterruptOffer($card)]
            : [];
    }
}
