<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\RevealCards;
use App\Model\InterruptOffer;
use App\Trigger\ProvinceBreaking;

/**
 * Upholding Authority.
 */
final readonly class UpholdingAuthority10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-upholding-authority';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RevealCards::class];
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
