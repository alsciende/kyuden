<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\UserType;
use App\Repository\GameRepository;
use App\State\Manager\GameStateManager;
use App\View\Model\GameInProgressPlayerView;
use App\View\Model\UserGameView;

/**
 * Builds the views of the games of a user, to find the games they were matched in and those they have played (HTTP
 * API and MCP tools): in two queries whatever their number (the games with their players, then the clans of their
 * strongholds).
 */
final readonly class UserGameViewFactory
{
    public function __construct(
        private GameRepository $gameRepository,
        private GameStateManager $gameStateManager,
    ) {
    }

    /**
     * @param list<Game> $games games the user plays, with their players and users (GameRepository::findByUser())
     *
     * @return list<UserGameView> in the order of the games
     */
    public function createAll(array $games, string $userId): array
    {
        $clans = $this->gameRepository->findStrongholdClans(array_map(fn (Game $game): string => $game->getId(), $games));

        return array_map(function (Game $game) use ($userId, $clans): UserGameView {
            $players = $game->getPlayers()->getValues();
            $player = array_find($players, fn (Player $player): bool => $player->getUserId() === $userId)
                ?? throw new \LogicException("The user {$userId} does not play the game {$game->getId()}.");
            $ended = $game->getCurrentPlace() === Game::STATE_ENDED;
            $state = $this->gameStateManager->getState($game);

            return new UserGameView(
                id: $game->getId(),
                playerId: $player->getId(),
                version: $game->getVersion(),
                ended: $ended,
                friendly: $game->isFriendly(),
                players: array_map(fn (Player $player): string => $player->getUserId(), $players),
                seats: array_map(fn (Player $player): GameInProgressPlayerView => new GameInProgressPlayerView(
                    userId: $player->getUserId(),
                    name: $player->getUser()->getName(),
                    clan: $clans[$player->getId()] ?? null,
                    bot: $player->getUser()->getType() === UserType::Bot,
                ), $players),
                endedAt: $ended ? $game->getUpdatedAt() : null,
                winnerId: $state->winnerId,
                victoryCondition: $state->victoryCondition,
            );
        }, $games);
    }
}
