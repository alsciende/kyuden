<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\FirstPlayerChangedEvent;
use App\Exception\Rules\AlreadyFirstPlayerException;
use App\Message\TakeFirstPlayerToken;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, for any player: the engine does not apply card effects.
 * The player order does not change, only the first player.
 */
#[AsMessageHandler]
final readonly class TakeFirstPlayerTokenHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(TakeFirstPlayerToken $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $target = $this->playerRepository->get($message->getTargetPlayerId(), $game);
        if ($this->gameStateManager->getState($game)->firstPlayerId === $target->getId()) {
            throw new AlreadyFirstPlayerException($target);
        }

        $this->gameStateManager->withState($game, function (GameState $state) use ($target): GameState {
            $state->firstPlayerId = $target->getId();

            return $state;
        });
        $this->eventDispatcher->dispatch(new FirstPlayerChangedEvent($game, $target));
    }
}
