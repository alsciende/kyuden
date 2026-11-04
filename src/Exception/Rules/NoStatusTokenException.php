<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NoStatusTokenException extends RulesException
{
    public function __construct()
    {
        parent::__construct('An ordinary character has no status token.');
    }
}
