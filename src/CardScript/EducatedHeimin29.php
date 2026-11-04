<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\FillProvince;
use App\Message\LookAtCards;

/**
 * Educated Heimin.
 */
final readonly class EducatedHeimin29 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '29-educated-heimin';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [FillProvince::class, LookAtCards::class];
    }
}
