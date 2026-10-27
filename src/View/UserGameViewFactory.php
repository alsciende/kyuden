<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\Game;
use App\Entity\Player;
use App\View\Model\UserGameView;

/**
 * Builds the view of a game of a user, to find the games they were matched in (HTTP API and MCP tools).
 */
final readonly class UserGameViewFactory
{
    public function create(Game $game, string $userId): UserGameView
    {
        $players = $game->getPlayers()->getValues();
        $player = array_find($players, fn (Player $player): bool => $player->getUserId() === $userId)
            ?? throw new \LogicException("The user {$userId} does not play the game {$game->getId()}.");

        return new UserGameView(
            id: $game->getId(),
            playerId: $player->getId(),
            version: $game->getVersion(),
            ended: $game->getCurrentPlace() === Game::STATE_ENDED,
            friendly: $game->isFriendly(),
            players: array_map(fn (Player $player): string => $player->getUserId(), $players),
        );
    }
}
