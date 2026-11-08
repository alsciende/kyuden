<?php

declare(strict_types=1);

namespace App\Exception\Rules;

/**
 * Only a game that has ended is replayed.
 */
class GameNotEndedException extends RulesException
{
    public function __construct()
    {
        parent::__construct('The game has not ended.');
    }
}
