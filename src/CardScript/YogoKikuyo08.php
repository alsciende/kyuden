<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCharacterIntoPlay;

/**
 * Yogo Kikuyo.
 */
final readonly class YogoKikuyo08 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '08-yogo-kikuyo';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }
}
