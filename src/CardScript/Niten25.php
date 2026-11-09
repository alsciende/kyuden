<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PutAttachmentIntoPlay;
use App\Message\ReturnCardToHand;

/**
 * Niten.
 */
final readonly class Niten25 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '25-niten';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutAttachmentIntoPlay::class, ReturnCardToHand::class];
    }
}
