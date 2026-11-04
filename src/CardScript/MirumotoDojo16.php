<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\InitiateDuel;
use App\Message\MoveFate;

/**
 * Mirumoto Dōjō.
 */
final readonly class MirumotoDojo16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-mirumoto-dojo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class, MoveFate::class];
    }
}
