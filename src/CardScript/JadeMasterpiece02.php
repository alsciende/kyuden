<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveFate;

/**
 * Jade Masterpiece.
 */
final readonly class JadeMasterpiece02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-jade-masterpiece';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
