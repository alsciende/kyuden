<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;

/**
 * Meek Informant.
 */
final readonly class MeekInformant02 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '02-meek-informant';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class];
    }
}
