<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\ClaimRing;

/**
 * Offerings to the Kami.
 */
final readonly class OfferingsToTheKami40 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '40-offerings-to-the-kami';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ClaimRing::class];
    }
}
