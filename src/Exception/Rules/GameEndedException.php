<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class GameEndedException extends RulesException
{
    public function __construct()
    {
        parent::__construct('The game has ended.');
    }
}
