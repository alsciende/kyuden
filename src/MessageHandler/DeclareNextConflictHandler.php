<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\NextConflictClaimedEvent;
use App\Exception\Rules\NoConflictOpportunityException;
use App\Message\DeclareNextConflict;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, for a player with a conflict opportunity left: the
 * engine does not apply card effects. The next conflict opportunity goes to the target (DeclareConflictEnterListener),
 * whatever the order; the order goes on after them.
 */
#[AsMessageHandler]
final readonly class DeclareNextConflictHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(DeclareNextConflict $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $target = $this->playerRepository->get($message->getTargetPlayerId(), $game);
        if ($this->playerStateManager->getState($target)->getConflictOpportunities() <= 0) {
            throw new NoConflictOpportunityException();
        }

        $this->gameStateManager->withState($game, function (GameState $state) use ($target): GameState {
            $state->nextConflictPlayerId = $target->getId();

            return $state;
        });
        $this->eventDispatcher->dispatch(new NextConflictClaimedEvent($game, $target));
    }
}
