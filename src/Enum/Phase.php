<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A phase of a round (timing-and-gameplay.md), as the cards name it ("After the conflict phase begins").
 */
enum Phase: string
{
    case Dynasty = 'dynasty';
    case Draw = 'draw';
    case Conflict = 'conflict';
    case Fate = 'fate';
}
