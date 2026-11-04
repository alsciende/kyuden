<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;

/**
 * Kakita Dōjō.
 */
final readonly class KakitaDojo16 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '16-kakita-dojo';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
