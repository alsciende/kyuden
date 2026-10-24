<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class DuelStatisticAlreadyDeclaredException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player has already declared the dueling statistic of their side.');
    }
}
