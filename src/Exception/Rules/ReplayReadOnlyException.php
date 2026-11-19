<?php

declare(strict_types=1);

namespace App\Exception\Rules;

/**
 * A replay is read only: it only follows the records of the game it replays.
 */
class ReplayReadOnlyException extends RulesException
{
    public function __construct()
    {
        parent::__construct('A replay is read only.');
    }
}
