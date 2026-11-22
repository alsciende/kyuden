<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class InterruptWindowOpenException extends RulesException
{
    public function __construct()
    {
        parent::__construct('An interrupt window is open: only the interrupts, their effects and passing are accepted until it closes.');
    }
}
