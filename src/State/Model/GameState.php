<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Entity\Player;

class GameState
{
    public ?string $firstPlayerId = null;

    public function setFirstPlayer(Player $player): self
    {
        $this->firstPlayerId = $player->getId();

        return $this;
    }
}
