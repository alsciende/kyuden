<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\InitiateDuel;

/**
 * Make Your Case.
 */
final readonly class MakeYourCase36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-make-your-case';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
