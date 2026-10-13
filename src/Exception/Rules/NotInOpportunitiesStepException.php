<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotInOpportunitiesStepException extends RulesException
{
    public function __construct(?string $place)
    {
        parent::__construct(sprintf('Game step [%s] has no opportunities to act.', $place ?? '<undefined>'));
    }
}
