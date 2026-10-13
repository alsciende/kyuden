<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NoHonorBidException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player has not bid honor this round.');
    }
}
