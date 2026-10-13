<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Entity\Player;

class GameState
{
    /**
     * Ids of the players in player order, fixed when the game starts. The first player changes, not the order.
     *
     * @var list<string>
     */
    public array $playerIds = [];

    public ?string $firstPlayerId = null;

    /**
     * Player who has the opportunity to act, in steps resolved in player order. Set when such a step begins.
     */
    public ?string $activePlayerId = null;

    public function setPlayerOrder(Player ...$players): self
    {
        $this->playerIds = array_values(array_map(fn (Player $player): string => $player->getId(), $players));

        return $this;
    }

    /**
     * Ids of the other players in player order, starting with the one after this player.
     *
     * @return list<string>
     */
    public function getPlayerIdsAfter(Player $player): array
    {
        $index = array_search($player->getId(), $this->playerIds, true);
        if ($index === false) {
            throw new \LogicException("Player {$player->getId()} is not in the player order.");
        }

        return [...array_slice($this->playerIds, $index + 1), ...array_slice($this->playerIds, 0, $index)];
    }

    public function getNextPlayerId(Player $player): string
    {
        return $this->getPlayerIdsAfter($player)[0] ?? throw new \LogicException('The game has a single player.');
    }

    public function setFirstPlayer(Player $player): self
    {
        $this->firstPlayerId = $player->getId();

        return $this;
    }

    public function setActivePlayer(Player $player): self
    {
        $this->activePlayerId = $player->getId();

        return $this;
    }

    /**
     * Steps resolved in player order begin with the first player.
     */
    public function activateFirstPlayer(): self
    {
        $this->activePlayerId = $this->firstPlayerId;

        return $this;
    }

    public function isActivePlayer(Player $player): bool
    {
        return $this->activePlayerId === $player->getId();
    }
}
