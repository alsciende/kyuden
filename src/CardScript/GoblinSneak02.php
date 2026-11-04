<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveFate;

/**
 * Goblin Sneak.
 */
final readonly class GoblinSneak02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-goblin-sneak';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
