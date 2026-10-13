<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class CannotPlayFromHandException extends RulesException
{
    public function __construct(?string $place)
    {
        parent::__construct(sprintf('Characters and attachments cannot be played from hand during game step [%s].', $place ?? '<undefined>'));
    }
}
