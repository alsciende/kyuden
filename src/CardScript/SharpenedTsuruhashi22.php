<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;
use App\Model\InterruptOffer;
use App\Trigger\CardLeavingPlayTrigger;

/**
 * Sharpened Tsuruhashi.
 */
final readonly class SharpenedTsuruhashi22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-sharpened-tsuruhashi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeavingPlayTrigger::class => 'onCardLeavingPlay'];
    }

    /**
     * When attached character is sacrificed.
     *
     * @return list<InterruptOffer>
     */
    public function onCardLeavingPlay(CardLeavingPlayTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->card === $card->getAttachedCharacter() && $trigger->sacrificed
            ? [new InterruptOffer($card)]
            : [];
    }
}
