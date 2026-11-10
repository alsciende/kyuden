<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;
use App\Message\PlayCharacterAsAttachment;

/**
 * Serene Ise Zumi.
 */
final readonly class SereneIseZumi29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-serene-ise-zumi';
    }

    #[\Override]
    public function getAbilityTimings(): array
    {
        return [AbilityTiming::Action];
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PlayCharacterAsAttachment::class];
    }
}
