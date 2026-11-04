<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlaceHonorTokens;

/**
 * Distinguished Dōjō.
 */
final readonly class DistinguishedDojo18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-distinguished-dojo';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlaceHonorTokens::class];
    }
}
