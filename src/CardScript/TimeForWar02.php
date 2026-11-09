<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutAttachmentIntoPlay;

/**
 * Time for War.
 */
final readonly class TimeForWar02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-time-for-war';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Reaction];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutAttachmentIntoPlay::class];
    }
}
