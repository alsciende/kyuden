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
     * Players who have the opportunity to act. Set when a step with opportunities begins: a single player in steps
     * resolved in player order, every player in steps where they act simultaneously.
     *
     * @var list<string>
     */
    public array $activePlayerIds = [];

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

    /**
     * The player becomes the only active player.
     */
    public function setActivePlayer(Player $player): self
    {
        $this->activePlayerIds = [$player->getId()];

        return $this;
    }

    /**
     * Steps resolved in player order begin with the first player.
     */
    public function activateFirstPlayer(): self
    {
        $this->activePlayerIds = $this->firstPlayerId === null ? [] : [$this->firstPlayerId];

        return $this;
    }

    /**
     * In steps where players act simultaneously, every player is active.
     */
    public function activateAllPlayers(): self
    {
        $this->activePlayerIds = $this->playerIds;

        return $this;
    }

    public function deactivatePlayer(Player $player): self
    {
        $this->activePlayerIds = array_values(array_filter(
            $this->activePlayerIds,
            fn (string $playerId): bool => $playerId !== $player->getId(),
        ));

        return $this;
    }

    public function isActivePlayer(Player $player): bool
    {
        return in_array($player->getId(), $this->activePlayerIds, true);
    }

    /**
     * Several active players act simultaneously. Not prefixed with `get`, `is` or `has`, so that the serializer of the
     * state does not take it for a property.
     */
    public function countActivePlayers(): int
    {
        return count($this->activePlayerIds);
    }
}
