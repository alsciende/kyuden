<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\InitiateDuel;

/**
 * Kakita Kaezin.
 */
final readonly class KakitaKaezin01 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '01-kakita-kaezin';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [InitiateDuel::class];
    }
}
