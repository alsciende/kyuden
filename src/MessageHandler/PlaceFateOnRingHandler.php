<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\FatePlacedOnRingEvent;
use App\Message\PlaceFateOnRing;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, on any ring: the engine does not apply card effects,
 * which decide on which rings fate is placed (the unclaimed ones, for every card so far).
 */
#[AsMessageHandler]
final readonly class PlaceFateOnRingHandler implements PlayerActionHandlerInterface
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

    public function __invoke(PlaceFateOnRing $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));

        $this->gameStateManager->withState($game, function (GameState $state) use ($message): GameState {
            $state->getRing($message->getRing())->fate += $message->getAmount();

            return $state;
        });
        $this->eventDispatcher->dispatch(new FatePlacedOnRingEvent($game, $message->getRing(), $message->getAmount()));
    }
}
