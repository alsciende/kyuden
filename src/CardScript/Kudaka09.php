<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Element;
use App\Event\RingClaimedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Kudaka.
 */
final readonly class Kudaka09 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '09-kudaka';
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
     * After you claim the [element-air] ring.
     *
     * @return list<ReactionOffer>
     */
    public function onRingClaimed(RingClaimedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            && $event->ring === Element::Air
            ? [new ReactionOffer($card)]
            : [];
    }
}
