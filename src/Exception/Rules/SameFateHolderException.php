<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class SameFateHolderException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Fate cannot move from a holder to itself.');
    }
}
