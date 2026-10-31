<?php

declare(strict_types=1);

namespace App\Exception\Rules;

use App\Entity\Player;

class AlreadyFirstPlayerException extends RulesException
{
    public function __construct(Player $player)
    {
        parent::__construct(sprintf('Player [%s] already has the first player token.', $player->getId()));
    }
}
