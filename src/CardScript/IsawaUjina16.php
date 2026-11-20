<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Element;
use App\Event\RingClaimedEvent;
use App\Message\RemoveFromGame;
use App\Model\ReactionOffer;

/**
 * Isawa Ujina.
 */
final readonly class IsawaUjina16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-isawa-ujina';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RemoveFromGame::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [RingClaimedEvent::class => 'onRingClaimed'];
    }

    /**
     * Forced: after the [element-void] ring is claimed.
     *
     * @return list<ReactionOffer>
     */
    public function onRingClaimed(RingClaimedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->ring === Element::Void
            ? [new ReactionOffer($card, forced: true)]
            : [];
    }
}
