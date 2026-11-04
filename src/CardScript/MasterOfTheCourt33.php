<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\DiscardStatusToken;

/**
 * Master of the Court.
 */
final readonly class MasterOfTheCourt33 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '33-master-of-the-court';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [DiscardStatusToken::class];
    }
}
