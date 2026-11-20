<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Phase;
use App\Event\PhaseBeganEvent;
use App\Message\FillProvince;
use App\Model\ReactionOffer;

/**
 * Daidoji Marketplace.
 */
final readonly class DaidojiMarketplace30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-daidoji-marketplace';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PhaseBeganEvent::class => 'onPhaseBegan'];
    }

    /**
     * After the conflict phase begins.
     *
     * @return list<ReactionOffer>
     */
    public function onPhaseBegan(PhaseBeganEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->phase === Phase::Conflict
            ? [new ReactionOffer($card)]
            : [];
    }
}
