<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\ConflictTypeSwitchedEvent;
use App\Exception\Rules\NoConflictException;
use App\Message\SwitchConflictType;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place during a conflict, from any player of the game: the engine does not apply card effects,
 * which decide who switches the conflict. The players declare the skills of the new type.
 */
#[AsMessageHandler]
final readonly class SwitchConflictTypeHandler implements PlayerActionHandlerInterface
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
        // during a conflict, from any player of the game
        if ($this->gameStateManager->getState($game)->conflict === null) {
            throw new NoConflictException();
        }
    }

    public function __invoke(SwitchConflictType $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $type = $this->gameStateManager->getState($game)->currentConflict()->type->switched();
        $this->gameStateManager->withState($game, function (GameState $state) use ($type): GameState {
            $state->currentConflict()->type = $type;

            return $state;
        });
        $this->eventDispatcher->dispatch(new ConflictTypeSwitchedEvent($game, $player, $type));
    }
}
