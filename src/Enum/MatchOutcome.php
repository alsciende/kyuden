<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How a match of a tournament has ended: won in a game, won by a bye (no opponent), won by forfeit (the opponent did not
 * join in time, or their account was deleted), or without a result: both players eliminated (neither joined in time,
 * or their game ended without a winner).
 */
enum MatchOutcome: string
{
    case Game = 'game';
    case Bye = 'bye';
    case Forfeit = 'forfeit';
    case NoResult = 'no_result';
}
