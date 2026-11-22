<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Model\InterruptOffer;
use App\Trigger\CardLeavingPlay;

/**
 * Gallant Quartermaster.
 */
final readonly class GallantQuartermaster24 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '24-gallant-quartermaster';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeavingPlay::class => 'onCardLeavingPlay'];
    }

    /**
     * When this character is sacrificed.
     *
     * @return list<InterruptOffer>
     */
    public function onCardLeavingPlay(CardLeavingPlay $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->card === $card && $trigger->sacrificed
            ? [new InterruptOffer($card)]
            : [];
    }
}
