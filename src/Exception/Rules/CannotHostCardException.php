<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

/**
 * Cards are placed under a card in play (a character, an attachment, a holding face up in a province, the stronghold) or
 * a province, never under itself.
 */
class CannotHostCardException extends RulesException
{
    public function __construct(PhysicalCard $host)
    {
        parent::__construct(sprintf('No card can be placed under card [%s].', $host->getId()));
    }
}
