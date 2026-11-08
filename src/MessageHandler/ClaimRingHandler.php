<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\RingClaimedEvent;
use App\Event\RingTakenEvent;
use App\Exception\Rules\ConflictTypeRequiredException;
use App\Exception\Rules\RingAlreadyClaimedException;
use App\Message\ClaimRing;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, for any player: the engine does not apply card effects,
 * which decide who claims which ring. A ring claimed by another player is taken from them (Roving Michibiku).
 */
#[AsMessageHandler]
final readonly class ClaimRingHandler implements PlayerActionHandlerInterface
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

    public function __invoke(ClaimRing $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $target = $this->playerRepository->get($message->getTargetPlayerId(), $game);

        $gameState = $this->gameStateManager->getState($game);
        $ring = $gameState->getRing($message->getRing());
        if ($ring->claimedById === $target->getId()) {
            throw new RingAlreadyClaimedException($message->getRing());
        }

        // a ring taken from another player keeps the type of the conflict it was claimed in
        $previousHolder = $ring->claimedById === null ? null : $game->getPlayer($ring->claimedById);
        $conflictType = $message->getConflictType()
            ?? $ring->claimedConflictType
            ?? $gameState->conflict->type
            ?? throw new ConflictTypeRequiredException();
        $this->gameStateManager->withState($game, function (GameState $state) use ($message, $target, $conflictType): GameState {
            $state->getRing($message->getRing())->claim($target->getId(), $conflictType);

            return $state;
        });
        $this->eventDispatcher->dispatch($previousHolder === null
            ? new RingClaimedEvent($game, $target, $message->getRing())
            : new RingTakenEvent($game, $target, $message->getRing(), $previousHolder));
    }
}
