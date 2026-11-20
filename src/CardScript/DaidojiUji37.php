<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardEnteredPlayEvent;
use App\Message\PlaceCardUnder;
use App\Message\RemoveFromGame;
use App\Message\SearchConflictDeck;
use App\Message\ShuffleConflictDeck;
use App\Model\ReactionOffer;

/**
 * Daidoji Uji.
 */
final readonly class DaidojiUji37 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '37-daidoji-uji';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceCardUnder::class, RemoveFromGame::class, SearchConflictDeck::class, ShuffleConflictDeck::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [CardEnteredPlayEvent::class => 'onCardEnteredPlay'];
    }

    /**
     * After this character enters play.
     *
     * @return list<ReactionOffer>
     */
    public function onCardEnteredPlay(CardEnteredPlayEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->card === $card
            ? [new ReactionOffer($card)]
            : [];
    }
}
