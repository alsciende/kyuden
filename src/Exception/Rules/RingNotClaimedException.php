<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Enum\Element;

class RingNotClaimedException extends RulesException
{
    public function __construct(Element $ring)
    {
        parent::__construct(sprintf('The %s ring is not in a claimed ring pool.', $ring->value));
    }
}
