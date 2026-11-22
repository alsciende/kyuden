<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotAwaitedInInterruptWindowException extends RulesException
{
    public function __construct()
    {
        parent::__construct('The interrupt window does not wait for this player.');
    }
}
