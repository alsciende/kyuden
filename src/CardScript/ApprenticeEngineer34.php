<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutHoldingIntoProvince;

/**
 * Apprentice Engineer.
 */
final readonly class ApprenticeEngineer34 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '34-apprentice-engineer';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutHoldingIntoProvince::class];
    }
}
