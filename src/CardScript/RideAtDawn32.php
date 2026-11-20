<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\DynastyPhasePassedEvent;
use App\Message\FillProvince;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Ride at Dawn.
 */
final readonly class RideAtDawn32 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '32-ride-at-dawn';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [DynastyPhasePassedEvent::class => 'onDynastyPhasePassed'];
    }

    /**
     * After you pass during the dynasty phase.
     *
     * @return list<ReactionOffer>
     */
    public function onDynastyPhasePassed(DynastyPhasePassedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->player === $this->triggerConditions->getController($card)
            ? [new ReactionOffer($card)]
            : [];
    }
}
