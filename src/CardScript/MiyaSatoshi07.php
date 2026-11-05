<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutCardIntoProvince;

/**
 * Miya Satoshi.
 */
final readonly class MiyaSatoshi07 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '07-miya-satoshi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCardIntoProvince::class];
    }
}
