<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveStatusToken;

/**
 * Hiruma Yoshino.
 */
final readonly class HirumaYoshino20 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '20-hiruma-yoshino';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveStatusToken::class];
    }
}
