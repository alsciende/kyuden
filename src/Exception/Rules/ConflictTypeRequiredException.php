<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class ConflictTypeRequiredException extends RulesException
{
    public function __construct()
    {
        parent::__construct('No conflict is being resolved: the conflict type the ring is claimed in is required.');
    }
}
