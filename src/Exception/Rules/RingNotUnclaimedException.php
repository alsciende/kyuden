<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class RingNotUnclaimedException extends RulesException
{
    public function __construct(string $element)
    {
        parent::__construct(sprintf('The %s ring is not in the unclaimed ring pool.', $element));
    }
}
