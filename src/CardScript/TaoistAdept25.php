<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\InitiateDuel;
use App\Message\MoveFate;

/**
 * Taoist Adept.
 */
final readonly class TaoistAdept25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-taoist-adept';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class, MoveFate::class];
    }
}
