<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
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
final readonly class CollectFateHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $stateManager,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $game->assertPlace('collect_fate');
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }

        if ($this->stateManager->getState($player)->isFateCollected()) {
            throw new FateAlreadyCollectedException();
        }
    }

    public function __invoke(CollectFate $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->stateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->incrementFate($message->getAmount())->setFateCollected(true),
        );
        $this->gameStateManager->withState(
            $game,
            fn (GameState $state): GameState => $state->setActivePlayer($game->getPlayer($state->getNextPlayerId($player))),
        );
    }
}
