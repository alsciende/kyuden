<?php

declare(strict_types=1);

namespace App\Enum;

enum Element: string
{
    case Air = 'air';
    case Earth = 'earth';
    case Fire = 'fire';
    case Void = 'void';
    case Water = 'water';
}
