<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class GameNotAbandonedException extends RulesException
{
    public function __construct(string $message = 'The game does not wait for your opponent: it waits for you, or for nobody.')
    {
        parent::__construct($message);
    }
}
