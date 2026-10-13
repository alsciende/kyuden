<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class HonorAlreadySetException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player honor has already been set.');
    }
}
