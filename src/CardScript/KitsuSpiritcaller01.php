<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutCardOnDeck;
use App\Message\PutCharacterIntoPlay;

/**
 * Kitsu Spiritcaller.
 */
final readonly class KitsuSpiritcaller01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-kitsu-spiritcaller';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardOnDeck::class, PutCharacterIntoPlay::class];
    }
}
