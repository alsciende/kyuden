<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

class AlreadyParticipatingException extends RulesException
{
    public function __construct(PhysicalCard $card)
    {
        parent::__construct(sprintf('Card [%s] already participates in the conflict.', $card->getId()));
    }
}
