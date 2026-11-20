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
 * Kami of Ancient Wisdom.
 */
final readonly class KamiOfAncientWisdom40 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '40-kami-of-ancient-wisdom';
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
     * After 1 or more fate is removed from a character outside the fate phase.
     *
     * @return list<ReactionOffer>
     */
    public function onFateRemovedFromCard(FateRemovedFromCardEvent $event, PhysicalCard $card, Game $game): array
    {
        return ! $event->leavingPlay
            && ! $this->triggerConditions->isFatePhase($game)
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After 1 or more fate is removed from a character outside the fate phase: moved from it.
     *
     * @return list<ReactionOffer>
     */
    public function onFateMoved(FateMovedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->source instanceof PhysicalCard
            && ! $this->triggerConditions->isFatePhase($game)
            ? [new ReactionOffer($card)]
            : [];
    }
}
