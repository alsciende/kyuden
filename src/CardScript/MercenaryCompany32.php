<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\MoveFate;

/**
 * Mercenary Company.
 */
final readonly class MercenaryCompany32 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '32-mercenary-company';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [MoveFate::class];
    }
}
