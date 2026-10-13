<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Personal honor status of a character (honored and dishonored status tokens).
 */
enum PersonalHonor: string
{
    case Ordinary = 'ordinary';
    case Honored = 'honored';
    case Dishonored = 'dishonored';
}
