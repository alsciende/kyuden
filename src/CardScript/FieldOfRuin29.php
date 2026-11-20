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
 * Field of Ruin.
 */
final readonly class FieldOfRuin29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-field-of-ruin';
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
