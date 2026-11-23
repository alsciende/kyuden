<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveAttachment;
use App\Model\InterruptOffer;
use App\Trigger\CardLeavingPlay;

/**
 * Tainted Koku.
 */
final readonly class TaintedKoku10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-tainted-koku';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveAttachment::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeavingPlay::class => 'onCardLeavingPlay'];
    }

    /**
     * When attached character leaves play.
     *
     * @return list<InterruptOffer>
     */
    public function onCardLeavingPlay(CardLeavingPlay $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->card === $card->getAttachedCharacter()
            ? [new InterruptOffer($card)]
            : [];
    }
}
