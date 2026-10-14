<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\FateAlreadyCollectedException;
use App\Exception\Rules\NotActivePlayerException;
use App\Message\CollectFate;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Players collect fate in player order: once the active player has collected, the next player becomes active.
 */
#[AsMessageHandler]
final readonly class CollectFateHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(CollectFate $message): void
    {
        $game = $this->gameRepository->get($message->getGameId(), 'collect_fate');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }

        $this->stateManager->withState(
            $player,
            function (PlayerState $state) use ($message): PlayerState {
                if ($state->isFateCollected()) {
                    throw new FateAlreadyCollectedException();
                }

                return $state->incrementFate($message->getAmount())->setFateCollected(true);
            },
        );
        $this->gameStateManager->withState(
            $game,
            fn (GameState $state): GameState => $state->setActivePlayer($game->getPlayer($state->getNextPlayerId($player))),
        );
    }
}
