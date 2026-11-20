<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ProvinceBrokenEvent;
use App\Message\FillProvince;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Repentant Legion.
 */
final readonly class RepentantLegion40 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '40-repentant-legion';
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
        return [ProvinceBrokenEvent::class => 'onProvinceBroken'];
    }

    /**
     * After you break a province during a conflict in which this character is participating.
     *
     * @return list<ReactionOffer>
     */
    public function onProvinceBroken(ProvinceBrokenEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->breaker === $you
            && $this->triggerConditions->isParticipating($card)
            ? [new ReactionOffer($card)]
            : [];
    }
}
