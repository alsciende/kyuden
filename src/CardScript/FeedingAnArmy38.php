<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Element;
use App\Enum\Phase;
use App\Event\PhaseBeganEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Feeding an Army.
 */
final readonly class FeedingAnArmy38 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '38-feeding-an-army';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PhaseBeganEvent::class => 'onPhaseBegan'];
    }

    /**
     * After the conflict phase begins (Air Role only).
     *
     * @return list<ReactionOffer>
     */
    public function onPhaseBegan(PhaseBeganEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->phase === Phase::Conflict
            && $this->triggerConditions->isElementOfRole($you, Element::Air)
            ? [new ReactionOffer($card)]
            : [];
    }
}
