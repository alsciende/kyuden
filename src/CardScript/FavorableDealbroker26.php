<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardEnteredPlayEvent;
use App\Message\PutCharacterIntoPlay;
use App\Message\SearchDynastyDeck;
use App\Message\ShuffleDynastyDeck;
use App\Model\ReactionOffer;

/**
 * Favorable Dealbroker.
 */
final readonly class FavorableDealbroker26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-favorable-dealbroker';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class, SearchDynastyDeck::class, ShuffleDynastyDeck::class];
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
