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
 * Akodo Motivator.
 */
final readonly class AkodoMotivator27 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '27-akodo-motivator';
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
     * After an opponent's card ability or ring effect discards 1 or more cards from your hand: offered for every discard from your hand (the engine does not know whose effect it applies).
     *
     * @return list<ReactionOffer>
     */
    public function onCardsDiscardedFromHand(CardsDiscardedFromHandEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            ? [new ReactionOffer($card)]
            : [];
    }
}
