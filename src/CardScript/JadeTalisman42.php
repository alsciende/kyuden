<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\CancelImminentTrigger;
use App\Model\InterruptOffer;
use App\Trigger\RingEffectResolvingTrigger;

/**
 * Jade Talisman.
 */
final readonly class JadeTalisman42 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '42-jade-talisman';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [RingEffectResolvingTrigger::class => 'onRingEffectResolving'];
    }

    /**
     * When a ring effect would initiate, if it chooses attached character as a target (not known to the engine: every ring effect).
     *
     * @return list<InterruptOffer>
     */
    public function onRingEffectResolving(RingEffectResolvingTrigger $trigger, PhysicalCard $card, Game $game): array
    {
        return $card->getAttachedCharacter() instanceof PhysicalCard
            ? [new InterruptOffer($card, would: true)]
            : [];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [CancelImminentTrigger::class];
    }
}
