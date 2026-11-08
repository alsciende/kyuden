<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\RingReturnedEvent;
use App\Exception\Rules\RingNotClaimedException;
use App\Message\ReturnRing;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, on a ring of any claimed ring pool: the engine does not
 * apply card effects, which decide whose ring returns.
 */
#[AsMessageHandler]
final readonly class ReturnRingHandler implements PlayerActionHandlerInterface
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

    public function __invoke(ReturnRing $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $player);

        if ($this->gameStateManager->getState($game)->getRing($message->getRing())->claimedById === null) {
            throw new RingNotClaimedException($message->getRing());
        }

        $this->gameStateManager->withState($game, function (GameState $state) use ($message): GameState {
            $state->getRing($message->getRing())->unclaim();

            return $state;
        });
        $this->eventDispatcher->dispatch(new RingReturnedEvent($game, $player, $message->getRing()));
    }
}
