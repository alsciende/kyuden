<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\RevealCards;

/**
 * Artisan Academy.
 */
final readonly class ArtisanAcademy01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-artisan-academy';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RevealCards::class];
    }
}
