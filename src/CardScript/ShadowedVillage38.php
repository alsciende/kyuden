<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\FateMovedEvent;
use App\Event\FateRemovedFromCardEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Shadowed Village.
 */
final readonly class ShadowedVillage38 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '38-shadowed-village';
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
            FateRemovedFromCardEvent::class => 'onFateRemovedFromCard',
            FateMovedEvent::class => 'onFateMoved',
        ];
    }

    /**
     * After 1 or more fate is removed from a character you control outside the fate phase.
     *
     * @return list<ReactionOffer>
     */
    public function onFateRemovedFromCard(FateRemovedFromCardEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return ! $event->leavingPlay
            && $this->triggerConditions->isControlledBy($event->card, $you)
            && ! $this->triggerConditions->isFatePhase($game)
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After 1 or more fate is removed from a character you control outside the fate phase: moved from it.
     *
     * @return list<ReactionOffer>
     */
    public function onFateMoved(FateMovedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->source instanceof PhysicalCard
            && $this->triggerConditions->isControlledBy($event->source, $you)
            && ! $this->triggerConditions->isFatePhase($game)
            ? [new ReactionOffer($card)]
            : [];
    }
}
