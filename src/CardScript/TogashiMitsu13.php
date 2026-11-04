<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutCardOnDeck;

/**
 * Togashi Mitsu.
 */
final readonly class TogashiMitsu13 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '13-togashi-mitsu';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class];
    }
}
