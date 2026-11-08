<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\FateRemovedFromRingEvent;
use App\Exception\Rules\NotEnoughFateException;
use App\Message\RemoveFateFromRing;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, on any ring: the engine does not apply card effects,
 * which decide from which rings fate is removed.
 */
#[AsMessageHandler]
final readonly class RemoveFateFromRingHandler implements PlayerActionHandlerInterface
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

    /**
     * @throws NotEnoughFateException if the ring holds less fate
     */
    public function __invoke(RemoveFateFromRing $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));

        $this->gameStateManager->withState($game, function (GameState $state) use ($message): GameState {
            $ring = $state->getRing($message->getRing());
            if ($message->getAmount() > $ring->fate) {
                throw new NotEnoughFateException($message->getAmount(), $ring->fate);
            }

            $ring->fate -= $message->getAmount();

            return $state;
        });
        $this->eventDispatcher->dispatch(new FateRemovedFromRingEvent($game, $message->getRing(), $message->getAmount()));
    }
}
