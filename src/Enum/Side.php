<?php

declare(strict_types=1);

namespace App\Enum;

enum Side: string
{
    case Conflict = 'conflict';
    case Dynasty = 'dynasty';
    case Province = 'province';
    case Role = 'role';
}
