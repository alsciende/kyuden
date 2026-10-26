<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

class NotADuplicateException extends RulesException
{
    public function __construct(PhysicalCard $card, PhysicalCard $copy, string $reason)
    {
        parent::__construct(sprintf('Card [%s] is not a duplicate of card [%s]: %s.', $card->getId(), $copy->getId(), $reason));
    }
}
