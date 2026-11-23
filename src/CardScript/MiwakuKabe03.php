<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\ShuffleIntoDeck;
use App\Model\InterruptOffer;
use App\Trigger\ProvinceBreaking;

/**
 * Miwaku Kabe.
 */
final readonly class MiwakuKabe03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-miwaku-kabe';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ShuffleIntoDeck::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ProvinceBreaking::class => 'onProvinceBreaking'];
    }

    /**
     * When this holding's province is broken.
     *
     * @return list<InterruptOffer>
     */
    public function onProvinceBreaking(ProvinceBreaking $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->province->getStack() === $card->getStack()
            ? [new InterruptOffer($card)]
            : [];
    }
}
