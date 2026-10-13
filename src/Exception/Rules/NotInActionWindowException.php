<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotInActionWindowException extends RulesException
{
    public function __construct(?string $place)
    {
        parent::__construct(sprintf('Game step [%s] is not an action window.', $place ?? '<undefined>'));
    }
}
