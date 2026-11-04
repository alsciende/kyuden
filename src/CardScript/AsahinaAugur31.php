<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\LookAtCard;

/**
 * Asahina Augur.
 */
final readonly class AsahinaAugur31 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '31-asahina-augur';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCard::class];
    }
}
