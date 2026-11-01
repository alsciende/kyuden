<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\PhysicalCard;
use App\Entity\Player;

class AlreadyControlledException extends RulesException
{
    public function __construct(PhysicalCard $card, Player $player)
    {
        parent::__construct(sprintf('Card [%s] is already controlled by player [%s].', $card->getId(), $player->getId()));
    }
}
