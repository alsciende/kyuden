<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\DetachAsCharacter;
use App\Message\PlayCharacterAsAttachment;
use App\Model\InterruptOffer;
use App\Trigger\CardLeavingPlay;

/**
 * Promising Youth.
 */
final readonly class PromisingYouth39 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '39-promising-youth';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DetachAsCharacter::class, PlayCharacterAsAttachment::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardLeavingPlay::class => 'onCardLeavingPlay'];
    }

    /**
     * When attached character leaves play (played as an attachment).
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
