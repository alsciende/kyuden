<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\FillProvince;
use App\Message\PutCardIntoProvince;

/**
 * Master of Many Lifetimes.
 */
final readonly class MasterOfManyLifetimes33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-master-of-many-lifetimes';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class, PutCardIntoProvince::class];
    }
}
