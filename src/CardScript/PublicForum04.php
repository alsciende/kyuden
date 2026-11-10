<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlaceHonorTokens;
use App\Message\RestoreProvince;

/**
 * Public Forum.
 */
final readonly class PublicForum04 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '04-public-forum';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceHonorTokens::class, RestoreProvince::class];
    }
}
