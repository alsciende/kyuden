<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class StrengthAlreadyDeclaredException extends RulesException
{
    public function __construct()
    {
        parent::__construct('The strength of this province has already been declared.');
    }
}
