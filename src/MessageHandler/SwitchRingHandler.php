<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\RingFateTakenEvent;
use App\Event\RingsSwitchedEvent;
use App\Exception\Rules\RingNotSwitchableException;
use App\Message\SwitchRing;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game: the engine does not apply card effects, which decide
 * who switches which rings. The place of a ring is the contested ring, the unclaimed ring pool or a claimed ring pool
 * with its conflict type: both rings take the place of the other. The contested ring is only switched with a ring of
 * the unclaimed or claimed ring pool of the attacking player (Rings), and the attacking player takes the fate on the
 * new contested ring; the conflict keeps its type.
 */
#[AsMessageHandler]
final readonly class SwitchRingHandler implements PlayerActionHandlerInterface
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

    public function __invoke(SwitchRing $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $ring = $message->getRing();
        $otherRing = $message->getOtherRing();
        $gameState = $this->gameStateManager->getState($game);
        $conflict = $gameState->conflict;
        // the ring that becomes the contested ring, if any
        $newContested = match ($conflict?->ring) {
            $ring => $otherRing,
            $otherRing => $ring,
            default => null,
        };
        if ($ring === $otherRing) {
            throw new RingNotSwitchableException($ring, $otherRing, 'it is the same ring');
        }

        if ($newContested === null && $gameState->getRing($ring)->claimedById === $gameState->getRing($otherRing)->claimedById) {
            throw new RingNotSwitchableException($ring, $otherRing, 'they are in the same ring pool');
        }

        if ($conflict !== null && $newContested !== null && ! in_array($gameState->getRing($newContested)->claimedById, [null, $conflict->attackingPlayerId], true)) {
            throw new RingNotSwitchableException($ring, $otherRing, 'the contested ring is only switched with a ring of the ring pools of the attacking player');
        }

        $fate = $newContested === null ? 0 : $gameState->getRing($newContested)->fate;
        $this->gameStateManager->withState($game, function (GameState $state) use ($ring, $otherRing, $newContested): GameState {
            $first = $state->getRing($ring);
            $second = $state->getRing($otherRing);
            [$first->claimedById, $second->claimedById] = [$second->claimedById, $first->claimedById];
            [$first->claimedConflictType, $second->claimedConflictType] = [$second->claimedConflictType, $first->claimedConflictType];
            if ($newContested !== null) {
                $state->currentConflict()->ring = $newContested;
                $state->getRing($newContested)->fate = 0;
            }

            return $state;
        });
        $this->eventDispatcher->dispatch(new RingsSwitchedEvent($game, $player, $ring, $otherRing));

        if ($conflict !== null && $newContested !== null && $fate > 0) {
            $attacker = $game->getPlayer($conflict->attackingPlayerId);
            $this->playerStateManager->withState($attacker, fn (PlayerState $state): PlayerState => $state->incrementFate($fate));
            $this->eventDispatcher->dispatch(new RingFateTakenEvent($game, $attacker, $newContested, $fate));
        }
    }
}
