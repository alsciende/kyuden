<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class DuelInProgressException extends RulesException
{
    public function __construct()
    {
        parent::__construct('A duel is being resolved: no action window is open until it ends.');
    }
}
