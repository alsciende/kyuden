<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlayCharacterAsAttachment;

/**
 * Togashi Acolyte.
 */
final readonly class TogashiAcolyte25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-togashi-acolyte';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlayCharacterAsAttachment::class];
    }
}
