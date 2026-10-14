<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotInDuelException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player is not involved in the duel.');
    }
}
