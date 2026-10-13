<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type of a conflict, given by the faceup side of the contested ring. Also the side the Imperial Favor is set to.
 */
enum ConflictType: string
{
    case Military = 'military';
    case Political = 'political';
}
