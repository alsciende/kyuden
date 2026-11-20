<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Enum\Phase;
use App\Event\PhaseBeganEvent;
use App\Model\ReactionOffer;

/**
 * Kuni Laboratory.
 */
final readonly class KuniLaboratory13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-kuni-laboratory';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PhaseBeganEvent::class => 'onPhaseBegan'];
    }

    /**
     * Forced: after the conflict phase begins.
     *
     * @return list<ReactionOffer>
     */
    public function onPhaseBegan(PhaseBeganEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->phase === Phase::Conflict
            ? [new ReactionOffer($card, forced: true)]
            : [];
    }
}
