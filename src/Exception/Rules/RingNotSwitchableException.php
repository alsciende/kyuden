<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Enum\Element;

class RingNotSwitchableException extends RulesException
{
    public function __construct(Element $ring, Element $otherRing, string $reason)
    {
        parent::__construct(sprintf('The %s ring cannot be switched with the %s ring: %s.', $ring->value, $otherRing->value, $reason));
    }
}
