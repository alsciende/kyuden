<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotEnoughFateException extends RulesException
{
    public function __construct(int $amount, int $fate)
    {
        parent::__construct(sprintf('Cannot spend or remove %d fate: there is only %d fate.', $amount, $fate));
    }
}
