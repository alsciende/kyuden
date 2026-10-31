<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

class SameProvinceException extends RulesException
{
    public function __construct(PhysicalCard $card, PhysicalCard $other)
    {
        parent::__construct(sprintf('Cards [%s] and [%s] are in the same province.', $card->getId(), $other->getId()));
    }
}
