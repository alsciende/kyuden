<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class RingNotSwitchableException extends RulesException
{
    public function __construct(string $element)
    {
        parent::__construct(sprintf('The %s ring is neither the contested ring nor in the claimed or unclaimed ring pool of the attacking player.', $element));
    }
}
