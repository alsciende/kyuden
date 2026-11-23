<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Message\MoveFate;
use App\Model\InterruptOffer;
use App\Trigger\FateRemovingTrigger;

/**
 * Embrace the Void.
 */
final readonly class EmbraceTheVoid02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-embrace-the-void';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [FateRemovingTrigger::class => 'onFateRemoving'];
    }

    /**
     * When 1 or more fate would be removed from attached character: instead, move that fate to your fate pool.
     *
     * @return list<InterruptOffer>
     */
    public function onFateRemoving(FateRemovingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $trigger->card === $card->getAttachedCharacter()
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class, MoveFate::class];
    }
}
