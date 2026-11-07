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

    /** The stronghold province of the opponent of the winner is broken. */
    case StrongholdProvinceBroken = 'stronghold_province_broken';

    /** The opponent of the winner has conceded the game. */
    case Concession = 'concession';

    /**
     * The opponent of the winner has stopped playing: the winner has ended the game (AbandonmentClaimer); or every player
     * has, and the platform has ended the game without a winner (AbandonedGamesEnder).
     */
    case Abandonment = 'abandonment';
}
