<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotEnoughFateException extends RulesException
{
    public function __construct(int $amount, int $fate)
    {
        parent::__construct(sprintf('Player cannot spend %d fate: their fate pool holds %d fate.', $amount, $fate));
    }
}
