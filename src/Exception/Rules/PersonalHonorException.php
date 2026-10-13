<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Enum\PersonalHonor;

class PersonalHonorException extends RulesException
{
    public function __construct(PersonalHonor $status)
    {
        parent::__construct(sprintf('A %s character cannot become %s again.', $status->value, $status->value));
    }
}
