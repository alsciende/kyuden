<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where a tournament stands: open to registrations until one hour before it begins, then locked (the bracket drawn),
 * in progress from its start, ended once it has its winner; or cancelled, by its organizer before it locks or for lack
 * of players.
 */
enum TournamentStatus: string
{
    case Open = 'open';
    case Locked = 'locked';
    case InProgress = 'in_progress';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    /**
     * A tournament still to be played: neither ended nor cancelled.
     *
     * @return list<self>
     */
    public static function upcomingOrInProgress(): array
    {
        return [self::Open, self::Locked, self::InProgress];
    }
}
