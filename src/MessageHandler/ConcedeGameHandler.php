<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\GameConcededEvent;
use App\Exception\Rules\GameNotStartedException;
use App\Message\ConcedeGame;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any place once the game has started, during a duel too: the player is eliminated (VictoryService), and
 * the automatic transition `end_game` ends the game. The reason stays in the payload of the recorded event.
 */
#[AsMessageHandler]
final readonly class ConcedeGameHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        if ($game->getCurrentPlace() === 'join_game') {
            throw new GameNotStartedException();
        }
    }

    public function __invoke(ConcedeGame $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->stateManager->withState($player, fn (PlayerState $state): PlayerState => $state->setConceded(true));
        $this->eventDispatcher->dispatch(new GameConcededEvent($game, $player, $message->getReason()));
    }
}
