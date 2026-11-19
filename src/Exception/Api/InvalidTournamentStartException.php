<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The start chosen for a tournament breaks a rule of the dates: on the hour or the half hour, at least 12 hours ahead.
 */
class InvalidTournamentStartException extends \RuntimeException
{
}
