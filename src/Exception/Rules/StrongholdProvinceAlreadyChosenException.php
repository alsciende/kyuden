<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class StrongholdProvinceAlreadyChosenException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player has already chosen their stronghold province.');
    }
}
