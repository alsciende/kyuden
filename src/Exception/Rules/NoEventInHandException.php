<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Enum\AbilityTiming;

class NoEventInHandException extends RulesException
{
    public function __construct(AbilityTiming $timing)
    {
        parent::__construct(sprintf('There is no [%s] event in hand.', $timing->value));
    }
}
