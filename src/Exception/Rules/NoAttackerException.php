<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NoAttackerException extends RulesException
{
    public function __construct()
    {
        parent::__construct('At least one character must be declared as an attacker.');
    }
}
