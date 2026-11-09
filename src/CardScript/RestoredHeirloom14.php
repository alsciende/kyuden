<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutAttachmentIntoPlay;

/**
 * Restored Heirloom.
 */
final readonly class RestoredHeirloom14 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '14-restored-heirloom';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Interrupt];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutAttachmentIntoPlay::class];
    }
}
