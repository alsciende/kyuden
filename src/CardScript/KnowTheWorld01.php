<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\MoveFate;
use App\Message\SwitchRing;

/**
 * Know the World.
 */
final readonly class KnowTheWorld01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-know-the-world';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class, SwitchRing::class];
    }
}
