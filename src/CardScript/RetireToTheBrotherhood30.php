<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ProvinceRevealedEvent;
use App\Message\PutCharacterIntoPlay;
use App\Message\RevealCards;
use App\Message\ShuffleDynastyDeck;
use App\Model\ReactionOffer;

/**
 * Retire to the Brotherhood.
 */
final readonly class RetireToTheBrotherhood30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-retire-to-the-brotherhood';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class, RevealCards::class, ShuffleDynastyDeck::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [ProvinceRevealedEvent::class => 'onProvinceRevealed'];
    }

    /**
     * After this province is revealed.
     *
     * @return list<ReactionOffer>
     */
    public function onProvinceRevealed(ProvinceRevealedEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->province->getId() === $card->getId()
            ? [new ReactionOffer($card)]
            : [];
    }
}
