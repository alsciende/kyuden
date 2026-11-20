<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Event\CharacterMovedToConflictEvent;
use App\Message\FillProvince;
use App\Model\ReactionOffer;

/**
 * Twilight Rider.
 */
final readonly class TwilightRider30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-twilight-rider';
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
        return [CharacterMovedToConflictEvent::class => 'onCharacterMovedToConflict'];
    }

    /**
     * After this character moves to a conflict.
     *
     * @return list<ReactionOffer>
     */
    public function onCharacterMovedToConflict(CharacterMovedToConflictEvent $event, PhysicalCard $card, Game $game): array
    {
        return $event->card === $card
            ? [new ReactionOffer($card)]
            : [];
    }
}
