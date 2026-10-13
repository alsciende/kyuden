<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotActivePlayerException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player does not have the opportunity to act.');
    }
}
