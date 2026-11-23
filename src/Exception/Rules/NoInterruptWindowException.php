<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NoInterruptWindowException extends RulesException
{
    public function __construct()
    {
        parent::__construct('No interrupt window is open.');
    }
}
