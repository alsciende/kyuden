<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

class CannotParticipateException extends RulesException
{
    public function __construct(PhysicalCard $card, string $reason)
    {
        parent::__construct(sprintf('Card [%s] cannot participate in the conflict: %s.', $card->getId(), $reason));
    }
}
