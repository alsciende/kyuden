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
 * Trusted Advisor.
 */
final readonly class TrustedAdvisor21 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '21-trusted-advisor';
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
     * After you gain fate from a ring during a conflict in which this character is participating: taken from the contested ring.
     *
     * @return list<ReactionOffer>
     */
    public function onRingFateTaken(RingFateTakenEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            && $this->triggerConditions->isParticipating($card)
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After you gain fate from a ring during a conflict in which this character is participating: moved from a ring by an effect.
     *
     * @return list<ReactionOffer>
     */
    public function onFateMoved(FateMovedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->source instanceof Element
            && $event->target === $you
            && $this->triggerConditions->isParticipating($card)
            ? [new ReactionOffer($card)]
            : [];
    }
}
