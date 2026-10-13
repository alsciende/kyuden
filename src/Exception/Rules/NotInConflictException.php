<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotInConflictException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player is neither the attacking nor the defending player of the conflict.');
    }
}
