<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

class CannotDuelException extends RulesException
{
    public function __construct(PhysicalCard $card, string $reason)
    {
        parent::__construct(sprintf('Card [%s] cannot be involved in the duel: %s.', $card->getId(), $reason));
    }
}
