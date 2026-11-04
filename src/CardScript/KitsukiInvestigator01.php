<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\LookAtCards;
use App\Message\MoveFate;

/**
 * Kitsuki Investigator.
 */
final readonly class KitsukiInvestigator01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-kitsuki-investigator';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [LookAtCards::class, MoveFate::class];
    }
}
