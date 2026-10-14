<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;
use App\Enum\AbilityTiming;

class WrongAbilityTimingException extends RulesException
{
    public function __construct(PhysicalCard $card, AbilityTiming $expectedTiming)
    {
        parent::__construct(sprintf(
            'Card [%s] has no [%s] ability.',
            $card->getId(),
            $expectedTiming->value,
        ));
    }
}
