<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NoFacedownProvinceException extends RulesException
{
    public function __construct()
    {
        parent::__construct('There is no facedown province to reveal.');
    }
}
