<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\ConflictType;
use App\Event\ProvinceBrokenEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Glorious Victory.
 */
final readonly class GloriousVictory16 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '16-glorious-victory';
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
     * After you break a province during a [conflict-military] conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onProvinceBroken(ProvinceBrokenEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->breaker === $you
            && $this->triggerConditions->isConflictOfType($game, ConflictType::Military)
            ? [new ReactionOffer($card)]
            : [];
    }
}
