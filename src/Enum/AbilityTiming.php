<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Timing command of a triggered ability printed on a card (forced or not).
 */
enum AbilityTiming: string
{
    case Action = 'action';
    case Interrupt = 'interrupt';
    case Reaction = 'reaction';
}
