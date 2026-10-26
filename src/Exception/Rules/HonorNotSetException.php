<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class HonorNotSetException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player has no honor yet: it is set when the game is set up.');
    }
}
