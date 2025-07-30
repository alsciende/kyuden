<?php

declare(strict_types=1);

namespace App\Enum;

enum Role: string
{
    case Seeker = 'seeker';
    case Keeper = 'keeper';
    case Air = 'air';
    case Earth = 'earth';
    case Fire = 'fire';
    case Void = 'void';
    case Water = 'water';
}
