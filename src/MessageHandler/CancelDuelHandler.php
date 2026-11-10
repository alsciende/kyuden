<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\DuelCancelledEvent;
use App\Exception\Rules\NoDuelException;
use App\Message\CancelDuel;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, while a duel is being resolved, whatever its step: the
 * engine does not apply card effects. The duel ends without result: the honor dials not revealed yet are forgotten,
 * and the players can act again in the action window.
 */
#[AsMessageHandler]
final readonly class CancelDuelHandler implements PlayerActionHandlerInterface
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
        if ($this->gameStateManager->getState($game)->duel === null) {
            throw new NoDuelException();
        }
    }

    public function __invoke(CancelDuel $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $duel = $this->gameStateManager->getState($game)->currentDuel();

        foreach ($duel->playerIds() as $playerId) {
            $this->playerStateManager->withState(
                $game->getPlayer($playerId),
                fn (PlayerState $state): PlayerState => $state->setPendingHonorBid(null),
            );
        }
        $this->gameStateManager->withState($game, function (GameState $state): GameState {
            $state->duel = null;

            return $state;
        });
        $this->eventDispatcher->dispatch(new DuelCancelledEvent($game));
    }
}
