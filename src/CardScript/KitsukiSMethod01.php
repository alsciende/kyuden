<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\ReturnCardToHand;

/**
 * Kitsuki's Method.
 */
final readonly class KitsukiSMethod01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-kitsuki-s-method';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [ReturnCardToHand::class];
    }
}
