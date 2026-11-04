<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveStatusToken;

/**
 * Origami Master.
 */
final readonly class OrigamiMaster06 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '06-origami-master';
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
