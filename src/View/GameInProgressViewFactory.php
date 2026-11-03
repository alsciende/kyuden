<?php

declare(strict_types=1);

namespace App\View;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\UserType;
use App\Repository\GameRepository;
use App\Service\Spectating\SpectatorRegistry;
use App\View\Model\GameInProgressPlayerView;
use App\View\Model\GameInProgressView;

/**
 * Builds the list of the games in progress, which an account may watch: in three queries whatever their number (the
 * games with their players, the clans of their strongholds, their spectators).
 */
final readonly class GameInProgressViewFactory
{
    public function __construct(
        private GameRepository $gameRepository,
        private SpectatorRegistry $spectatorRegistry,
    ) {
    }

    /**
     * @return list<GameInProgressView> the last started first
     */
    public function createAll(): array
    {
        $games = $this->gameRepository->findInProgress();
        $gameIds = array_map(fn (Game $game): string => $game->getId(), $games);
        $clans = $this->gameRepository->findStrongholdClans($gameIds);
        $spectators = $this->spectatorRegistry->count($gameIds);

        return array_map(fn (Game $game): GameInProgressView => new GameInProgressView(
            id: $game->getId(),
            version: $game->getVersion(),
            place: $game->getCurrentPlace(),
            friendly: $game->isFriendly(),
            players: array_map(fn (Player $player): GameInProgressPlayerView => new GameInProgressPlayerView(
                userId: $player->getUserId(),
                name: $player->getUser()->getName(),
                clan: $clans[$player->getId()] ?? null,
                bot: $player->getUser()->getType() === UserType::Bot,
            ), $game->getPlayers()->getValues()),
            spectators: $spectators[$game->getId()] ?? 0,
        ), $games);
    }
}
