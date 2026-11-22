<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Trigger\CardLeavingPlay;

/**
 * Akodo Kaede.
 */
final readonly class AkodoKaede20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-akodo-kaede';
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
     * When another character would leave play.
     *
     * @return list<InterruptOffer>
     */
    public function onCardLeavingPlay(CardLeavingPlay $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->character && $trigger->card !== $card
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
