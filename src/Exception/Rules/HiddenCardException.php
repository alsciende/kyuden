<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

class HiddenCardException extends RulesException
{
    public function __construct(PhysicalCard $card)
    {
        parent::__construct(sprintf('Card [%s] is in a stack hidden from some players.', $card->getId()));
    }
}
