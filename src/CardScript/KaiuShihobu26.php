<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CardEnteredPlayEvent;
use App\Message\PlaceCardUnder;
use App\Message\PutHoldingIntoProvince;
use App\Message\RevealCards;
use App\Message\SearchDynastyDeck;
use App\Message\ShuffleDynastyDeck;
use App\Model\ReactionOffer;

/**
 * Kaiu Shihobu.
 */
final readonly class KaiuShihobu26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-kaiu-shihobu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action, AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceCardUnder::class, PutHoldingIntoProvince::class, RevealCards::class, SearchDynastyDeck::class, ShuffleDynastyDeck::class];
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
