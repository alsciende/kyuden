<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\InitiateDuel;

/**
 * Kakita Toshimoko.
 */
final readonly class KakitaToshimoko16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-kakita-toshimoko';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
