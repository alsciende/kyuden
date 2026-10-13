<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NoConflictException extends RulesException
{
    public function __construct()
    {
        parent::__construct('No conflict is being resolved.');
    }
}
