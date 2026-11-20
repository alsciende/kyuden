<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

/**
 * An event played from a province is played during the dynasty phase only (glossary, Event Cards).
 */
class NotInDynastyPhaseException extends RulesException
{
    public function __construct(PhysicalCard $card, ?string $place)
    {
        parent::__construct(sprintf('Card [%s] is played from a province during the dynasty phase only, not in game step [%s].', $card->getId(), $place ?? '<undefined>'));
    }
}
