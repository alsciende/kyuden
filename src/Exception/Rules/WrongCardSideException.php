<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;
use App\Enum\Side;

/**
 * The card is not of the expected side, e.g. a province where a dynasty card is expected.
 */
class WrongCardSideException extends RulesException
{
    public function __construct(
        PhysicalCard $card,
        Side $expectedSide,
    ) {
        parent::__construct(sprintf(
            'Card [%s] of side [%s] is not of expected side [%s]',
            $card->getId(),
            $card->getLogicalCard()->getSide()->value,
            $expectedSide->value,
        ));
    }
}
