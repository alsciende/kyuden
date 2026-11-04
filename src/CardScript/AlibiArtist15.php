<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\AddCardToHand;
use App\Message\LookAtCards;
use App\Message\PutCardOnDeck;

/**
 * Alibi Artist.
 */
final readonly class AlibiArtist15 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '15-alibi-artist';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [AddCardToHand::class, LookAtCards::class, PutCardOnDeck::class];
    }
}
