<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\DynastyPhasePassedEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Shinjo Scout.
 */
final readonly class ShinjoScout07 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '07-shinjo-scout';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [DynastyPhasePassedEvent::class => 'onDynastyPhasePassed'];
    }

    /**
     * After you gain fate for passing in the dynasty phase.
     *
     * @return list<ReactionOffer>
     */
    public function onDynastyPhasePassed(DynastyPhasePassedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            && $event->fateGained > 0
            ? [new ReactionOffer($card)]
            : [];
    }
}
