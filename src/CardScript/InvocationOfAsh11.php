<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveAttachment;

/**
 * Invocation of Ash.
 */
final readonly class InvocationOfAsh11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-invocation-of-ash';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveAttachment::class];
    }
}
