<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\PhaseBeganEvent;
use App\Message\MoveFate;
use App\Model\ReactionOffer;

/**
 * Agasha Hiyori.
 */
final readonly class AgashaHiyori26 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '26-agasha-hiyori';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [PhaseBeganEvent::class => 'onPhaseBegan'];
    }

    /**
     * After a phase begins.
     *
     * @return list<ReactionOffer>
     */
    public function onPhaseBegan(PhaseBeganEvent $event, PhysicalCard $card, Game $game): array
    {
        return [new ReactionOffer($card)];
    }
}
