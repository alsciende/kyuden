<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class GameNotStartedException extends RulesException
{
    public function __construct()
    {
        parent::__construct('The game has not started yet.');
    }
}
