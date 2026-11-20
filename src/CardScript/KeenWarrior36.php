<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardsLookedAtEvent;
use App\Event\CardsRevealedEvent;
use App\Message\PutCardOnDeck;
use App\Model\ReactionOffer;
use App\Service\CardScript\TriggerConditions;

/**
 * Keen Warrior.
 */
final readonly class KeenWarrior36 extends AbstractCardScript
{
    public function __construct(
        private TriggerConditions $triggerConditions,
    ) {
    }

    #[\Override]
    public static function getCode(): string
    {
        return '36-keen-warrior';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            CardsLookedAtEvent::class => 'onCardsLookedAt',
            CardsRevealedEvent::class => 'onCardsRevealed',
        ];
    }

    /**
     * After you look at 1 or more cards in an opponent's hand.
     *
     * @return list<ReactionOffer>
     */
    public function onCardsLookedAt(CardsLookedAtEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->player === $you
            && $event->isHandOf($this->triggerConditions->getOpponent($you))
            ? [new ReactionOffer($card)]
            : [];
    }

    /**
     * After 1 or more cards in an opponent's hand are revealed.
     *
     * @return list<ReactionOffer>
     */
    public function onCardsRevealed(CardsRevealedEvent $event, PhysicalCard $card, Game $game): array
    {
        $you = $this->triggerConditions->getController($card);

        return $event->isHandOf($this->triggerConditions->getOpponent($you))
            ? [new ReactionOffer($card)]
            : [];
    }
}
