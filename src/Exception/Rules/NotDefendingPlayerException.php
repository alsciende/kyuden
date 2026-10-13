<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotDefendingPlayerException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player is not the defending player of the conflict.');
    }
}
