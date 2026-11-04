<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveAttachment;

/**
 * Tainted Koku.
 */
final readonly class TaintedKoku10 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '10-tainted-koku';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveAttachment::class];
    }
}
