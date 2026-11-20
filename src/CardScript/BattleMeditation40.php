<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ProvinceBrokenEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Battle Meditation.
 */
final readonly class BattleMeditation40 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '40-battle-meditation';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ProvinceBrokenEvent::class => 'onProvinceBroken'];
    }

    /**
     * After you break a province during a conflict, if you control a participating Berserker character.
     *
     * @return list<ReactionOffer>
     */
    public function onProvinceBroken(ProvinceBrokenEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->breaker === $you
            && array_filter($this->triggerConditions->getParticipants($you), fn (PhysicalCard $participant): bool => $this->triggerConditions->hasTrait($participant, 'berserker')) !== []
            ? [new ReactionOffer($card)]
            : [];
    }
}
