<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class FateAlreadyCollectedException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player has already collected fate this round.');
    }
}
