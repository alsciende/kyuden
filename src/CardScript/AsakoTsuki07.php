<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Element;
use App\Event\RingClaimedEvent;
use App\Model\ReactionOffer;

/**
 * Asako Tsuki.
 */
final readonly class AsakoTsuki07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-asako-tsuki';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [RingClaimedEvent::class => 'onRingClaimed'];
    }

    /**
     * After the [element-water] ring is claimed.
     *
     * @return list<ReactionOffer>
     */
    public function onRingClaimed(RingClaimedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->ring === Element::Water
            ? [new ReactionOffer($card)]
            : [];
    }
}
