<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutCharacterIntoPlay;
use App\Message\RevealCard;

/**
 * Stoke Insurrection.
 */
final readonly class StokeInsurrection33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-stoke-insurrection';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class, RevealCard::class];
    }
}
