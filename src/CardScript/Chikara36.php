<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveFate;

/**
 * Chikara.
 */
final readonly class Chikara36 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '36-chikara';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function isAbilityGrantedToAttached(): bool
    {
        return true;
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
