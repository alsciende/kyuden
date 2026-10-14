<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statistic compared in a duel (D.2), given by the ability that initiates it.
 */
enum DuelType: string
{
    case Military = 'military';
    case Political = 'political';
    case Glory = 'glory';
}
