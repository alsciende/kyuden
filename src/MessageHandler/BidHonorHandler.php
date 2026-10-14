<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\HonorBidPlacedEvent;
use App\Exception\Data\WorkflowException;
use App\Exception\Rules\DuelStepException;
use App\Exception\Rules\HonorAlreadyBidException;
use App\Exception\Rules\NotInDuelException;
use App\Message\BidHonor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Players bid simultaneously: no player order, each player bids once per honor bid. The bid stays secret (pending)
 * until the honor dials are revealed. Allowed during the honor bid of the draw phase (2.2), or during a duel (D.3) from
 * the players involved in it.
 */
#[AsMessageHandler]
final readonly class BidHonorHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(BidHonor $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $duel = $this->gameStateManager->getState($game)->duel;
        if ($duel !== null) {
            if (! $duel->atBidStep()) {
                throw new DuelStepException('honor bid');
            }

            if (! $duel->involves($player->getId())) {
                throw new NotInDuelException();
            }
        } elseif ($game->getCurrentPlace() !== 'honor_bid') {
            throw new WorkflowException('honor_bid', $game->getCurrentPlace() ?? '<undefined>');
        }

        $this->stateManager->withState(
            $player,
            function (PlayerState $state) use ($message): PlayerState {
                if ($state->getPendingHonorBid() !== null) {
                    throw new HonorAlreadyBidException();
                }

                return $state->setPendingHonorBid($message->getBid());
            },
        );
        $this->eventDispatcher->dispatch(new HonorBidPlacedEvent($game, $player));
    }
}
