<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class HonorAlreadyBidException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player has already bid honor this round.');
    }
}
