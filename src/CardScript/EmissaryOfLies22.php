<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\RevealCards;

/**
 * Emissary of Lies.
 */
final readonly class EmissaryOfLies22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-emissary-of-lies';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RevealCards::class];
    }
}
