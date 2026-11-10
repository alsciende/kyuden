<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class AttackedProvinceFacedownException extends RulesException
{
    public function __construct()
    {
        parent::__construct('An attacked province is facedown: reveal it first (RevealProvince).');
    }
}
