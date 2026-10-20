<?php

declare(strict_types=1);

namespace App\Exception\Rules;

/**
 * A player who has played nothing has nothing to end: they act, or pass (PassOpportunity).
 */
class NothingToResolveException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player is resolving nothing: they act or pass.');
    }
}
