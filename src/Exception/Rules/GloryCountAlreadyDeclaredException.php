<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class GloryCountAlreadyDeclaredException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player has already declared their glory count.');
    }
}
