<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\ReturnCardToHand;

/**
 * Adept of Shadows.
 */
final readonly class AdeptOfShadows01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-adept-of-shadows';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ReturnCardToHand::class];
    }
}
