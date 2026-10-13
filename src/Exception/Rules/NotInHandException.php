<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

class NotInHandException extends RulesException
{
    public function __construct(PhysicalCard $card)
    {
        parent::__construct(sprintf('Card [%s] is not in hand.', $card->getId()));
    }
}
