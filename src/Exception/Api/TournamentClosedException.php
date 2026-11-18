<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The tournament no longer changes: locked one hour before it begins, or cancelled.
 */
class TournamentClosedException extends \RuntimeException
{
}
