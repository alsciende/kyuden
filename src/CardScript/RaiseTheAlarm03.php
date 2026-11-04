<?php

declare(strict_types=1);

namespace App\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Message\PutCharacterIntoPlay;

/**
 * Raise the Alarm.
 */
final readonly class RaiseTheAlarm03 extends AbstractCardScript
{
    #[\Override]
    public static function getCode(): string
    {
        return '03-raise-the-alarm';
    }

    #[\Override]
    public function getSpecialActions(PhysicalCard $card, Game $game): array
    {
        return [PutCharacterIntoPlay::class];
    }
}
