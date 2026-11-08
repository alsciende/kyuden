<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NoFaceupProvinceException extends RulesException
{
    public function __construct()
    {
        parent::__construct('There is no unbroken faceup province to turn facedown.');
    }
}
