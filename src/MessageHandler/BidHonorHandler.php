<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\HonorBidPlacedEvent;
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
final readonly class BidHonorHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $duel = $this->gameStateManager->getState($game)->duel;
        if ($duel !== null) {
            if (! $duel->atBidStep()) {
                throw new DuelStepException('honor bid');
            }

            if (! $duel->involves($player->getId())) {
                throw new NotInDuelException();
            }
        } else {
            $game->assertPlace('honor_bid');
        }

        if ($this->stateManager->getState($player)->getPendingHonorBid() !== null) {
            throw new HonorAlreadyBidException();
        }
    }

    public function __invoke(BidHonor $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->stateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->setPendingHonorBid($message->getBid()),
        );
        $this->eventDispatcher->dispatch(new HonorBidPlacedEvent($game, $player, $message->getBid()));
    }
}
