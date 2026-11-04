<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Kitsuki Jusai.
 */
final readonly class KitsukiJusai18 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '18-kitsuki-jusai';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
