<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\ProvinceRevealedEvent;
use App\Message\PutCardIntoProvince;
use App\Message\SearchDynastyDeck;
use App\Message\ShuffleDynastyDeck;
use App\Model\ReactionOffer;

/**
 * Emperor's Summons.
 */
final readonly class EmperorSSummons22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-emperor-s-summons';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardIntoProvince::class, SearchDynastyDeck::class, ShuffleDynastyDeck::class];
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
