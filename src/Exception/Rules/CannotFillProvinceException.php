<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

class CannotFillProvinceException extends RulesException
{
    public function __construct(PhysicalCard $province, string $reason)
    {
        parent::__construct(sprintf('Province [%s] cannot be filled: %s', $province->getId(), $reason));
    }
}
