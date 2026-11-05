<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\LookAtCard;
use App\Message\PutCardIntoProvince;
use App\Message\SwitchProvinceCards;

/**
 * Governor's Spy.
 */
final readonly class GovernorSSpy29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-governor-s-spy';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCard::class, PutCardIntoProvince::class, SwitchProvinceCards::class];
    }
}
