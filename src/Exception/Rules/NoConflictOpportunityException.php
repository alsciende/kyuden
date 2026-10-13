<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Enum\ConflictType;

class NoConflictOpportunityException extends RulesException
{
    public function __construct(?ConflictType $type = null)
    {
        parent::__construct($type instanceof ConflictType
            ? sprintf('Player has no opportunity to declare a %s conflict.', $type->value)
            : 'Player has no conflict opportunity left.');
    }
}
