<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\GameAbandonedEvent;
use App\Exception\Rules\GameNotStartedException;
use App\Message\EndAbandonedGame;
use App\Repository\GameRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Every player has abandoned the game: they are all eliminated, and the automatic transition `end_game` ends it without
 * a winner (VictoryService). Nothing is checked here but that the game has started (an ended game is rejected by
 * GameEndedMiddleware), so that the events replay: how long the players have been gone is checked before the message is
 * sent (AbandonedGamesEnder).
 */
#[AsMessageHandler]
final readonly class EndAbandonedGameHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(EndAbandonedGame $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        if (in_array($game->getCurrentPlace(), [null, 'join_game'], true)) {
            throw new GameNotStartedException();
        }

        foreach ($game->getPlayers() as $player) {
            $this->stateManager->withState($player, fn (PlayerState $state): PlayerState => $state->setAbandoned(true));
            $this->eventDispatcher->dispatch(new GameAbandonedEvent($game, $player));
        }
    }
}
