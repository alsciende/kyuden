<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;
use App\Entity\Player;

class NotInHandException extends RulesException
{
    public function __construct(PhysicalCard $card, Player $player)
    {
        parent::__construct(sprintf('Card [%s] is not in the hand of player [%s].', $card->getId(), $player->getId()));
    }
}
