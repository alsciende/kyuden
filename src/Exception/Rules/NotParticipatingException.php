<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

class NotParticipatingException extends RulesException
{
    public function __construct(PhysicalCard $card)
    {
        parent::__construct(sprintf('Card [%s] does not participate in the conflict.', $card->getId()));
    }
}
