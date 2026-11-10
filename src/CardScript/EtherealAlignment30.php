<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\RemoveFromGame;
use App\Message\RestoreProvince;

/**
 * Ethereal Alignment.
 */
final readonly class EtherealAlignment30 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '30-ethereal-alignment';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [RemoveFromGame::class, RestoreProvince::class];
    }
}
