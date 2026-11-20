<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardsDiscardedFromHandEvent;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Shosuro Hyobu.
 */
final readonly class ShosuroHyobu17 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '17-shosuro-hyobu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardsDiscardedFromHandEvent::class => 'onCardsDiscardedFromHand'];
    }

    /**
     * After an opponent discards 1 or more cards from his or her hand due to the cost or effect of a card ability.
     *
     * @return list<ReactionOffer>
     */
    public function onCardsDiscardedFromHand(CardsDiscardedFromHandEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player !== $you
            ? [new ReactionOffer($card)]
            : [];
    }
}
