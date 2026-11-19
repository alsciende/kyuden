<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * A match of a tournament cannot be joined: unknown, not of the current round, not of this user, played already, or
 * the time to join it is over.
 */
class InvalidTournamentMatchException extends \RuntimeException
{
}
