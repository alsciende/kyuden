<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Stronghold format victory conditions met by the winner.
 */
enum VictoryCondition: string
{
    /** The winner has 25 or more honor. */
    case HonorWin = 'honor_win';
    /** The opponent of the winner has no honor left. */
    case HonorLoss = 'honor_loss';
}
