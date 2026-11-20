<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\FatePhaseFateRemovedEvent;
use App\Model\ReactionOffer;

/**
 * Starry Heaven Sanctuary.
 */
final readonly class StarryHeavenSanctuary22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-starry-heaven-sanctuary';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [FatePhaseFateRemovedEvent::class => 'onFatePhaseFateRemoved'];
    }

    /**
     * After fate is removed from characters in the fate phase.
     *
     * @return list<ReactionOffer>
     */
    public function onFatePhaseFateRemoved(FatePhaseFateRemovedEvent $event, PhysicalCard $card, Game $game): array
    {
        return [new ReactionOffer($card)];
    }
}
