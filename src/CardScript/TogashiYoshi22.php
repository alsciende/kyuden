<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Togashi Yoshi.
 */
final readonly class TogashiYoshi22 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '22-togashi-yoshi';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
