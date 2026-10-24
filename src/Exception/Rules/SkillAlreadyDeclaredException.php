<?php

declare(strict_types=1);

namespace App\Exception\Rules;

class SkillAlreadyDeclaredException extends RulesException
{
    public function __construct()
    {
        parent::__construct('Player has already declared the skill of their side.');
    }
}
