<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;

/**
 * A card is played from a hand, a deck or a discard pile (a conflict card), or from a province, a deck or a discard
 * pile (a dynasty card), or from out of play (removed from the game, under a card): not from play nor from a facedown
 * position in a province.
 */
class NotPlayableException extends RulesException
{
    public function __construct(PhysicalCard $card)
    {
        parent::__construct(sprintf('Card [%s] cannot be played from where it is.', $card->getId()));
    }
}
