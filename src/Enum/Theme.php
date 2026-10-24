<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Whether the web client is light or dark for a user.
 */
enum Theme: string
{
    /** as the system of the user says */
    case Auto = 'auto';
    /** day: ink on paper */
    case Light = 'light';
    /** night: light ink on a dark ground */
    case Dark = 'dark';
}
