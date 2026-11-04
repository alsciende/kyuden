<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutCharacterIntoPlay;

/**
 * Benevolent Host.
 */
final readonly class BenevolentHost18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-benevolent-host';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }
}
