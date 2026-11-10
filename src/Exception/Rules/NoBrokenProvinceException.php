<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NoBrokenProvinceException extends RulesException
{
    public function __construct()
    {
        parent::__construct('There is no broken province to restore.');
    }
}
