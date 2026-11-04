<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class ImperialFavorUnclaimedException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Nobody has the Imperial Favor.');
    }
}
