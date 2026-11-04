<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\ShuffleConflictDeck;

/**
 * Seeker Initiate.
 */
final readonly class SeekerInitiate01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-seeker-initiate';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, ShuffleConflictDeck::class];
    }
}
