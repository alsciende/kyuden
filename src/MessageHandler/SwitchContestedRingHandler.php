<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\ContestedRingSwitchedEvent;
use App\Event\RingFateTakenEvent;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\RingNotSwitchableException;
use App\Message\SwitchContestedRing;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place during a conflict, from any player of the game: the engine does not apply card effects,
 * which decide who switches the ring. Only with a ring of the unclaimed or claimed ring pool of the attacking player
 * (Rings): the contested ring takes its place in that pool, and the attacking player takes the fate on the new
 * contested ring.
 */
#[AsMessageHandler]
final readonly class SwitchContestedRingHandler implements PlayerActionHandlerInterface
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
        // during a conflict, from any player of the game
        if ($this->gameStateManager->getState($game)->conflict === null) {
            throw new NoConflictException();
        }
    }

    public function __invoke(SwitchContestedRing $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $gameState = $this->gameStateManager->getState($game);
        $conflict = $gameState->currentConflict();
        $from = $conflict->ring;
        $ring = $gameState->getRing($message->getRing());
        if ($message->getRing() === $from || ! in_array($ring->claimedById, [null, $conflict->attackingPlayerId], true)) {
            throw new RingNotSwitchableException($message->getRing()->value);
        }

        $attacker = $game->getPlayer($conflict->attackingPlayerId);
        $this->gameStateManager->withState($game, function (GameState $state) use ($message, $from, $ring): GameState {
            // the contested ring goes to the pool the new one comes from
            $state->getRing($from)->claimedById = $ring->claimedById;
            $state->getRing($message->getRing())->claimedById = null;
            $state->getRing($message->getRing())->fate = 0;
            $state->currentConflict()->ring = $message->getRing();

            return $state;
        });
        $this->eventDispatcher->dispatch(new ContestedRingSwitchedEvent($game, $player, $from, $message->getRing()));

        if ($ring->fate > 0) {
            $this->playerStateManager->withState($attacker, fn (PlayerState $state): PlayerState => $state->incrementFate($ring->fate));
            $this->eventDispatcher->dispatch(new RingFateTakenEvent($game, $attacker, $message->getRing(), $ring->fate));
        }
    }
}
