<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class NotAttackedProvinceException extends RulesException
{
    public function __construct()
    {
        parent::__construct('The province is not attacked in the conflict.');
    }
}
