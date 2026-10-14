<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class DuelStepException extends RulesException
{
    public function __construct(string $step)
    {
        parent::__construct(sprintf('No duel is at the %s step.', $step));
    }
}
