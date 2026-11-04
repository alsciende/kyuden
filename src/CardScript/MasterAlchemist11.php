<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveFate;

/**
 * Master Alchemist.
 */
final readonly class MasterAlchemist11 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '11-master-alchemist';
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
