<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Element;
use App\Event\FateMovedEvent;
use App\Event\RingFateTakenEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Four Temples Advisor.
 */
final readonly class FourTemplesAdvisor25 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '25-four-temples-advisor';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            RingFateTakenEvent::class => 'onRingFateTaken',
            FateMovedEvent::class => 'onFateMoved',
        ];
    }

    /**
     * After you gain fate from a ring: taken from the contested ring.
     *
     * @return list<ReactionOffer>
     */
    public function onRingFateTaken(RingFateTakenEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After you gain fate from a ring: moved from a ring by an effect.
     *
     * @return list<ReactionOffer>
     */
    public function onFateMoved(FateMovedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->source instanceof Element
            && $event->target === $you
            ? [new ReactionOffer($card)]
            : [];
    }
}
