<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\NoHonorBidException;
use App\Message\SetHonorBid;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place, from any player of the game, on the dial of any player: the engine does not apply card
 * effects, which decide whose bid changes and when. The honor dial of the player must have been revealed. Card effects can set a bid
 * outside the range of the honor dial (e.g. 0).
 */
#[AsMessageHandler]
final readonly class SetHonorBidHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(SetHonorBid $message): void
    {
        if ($message->getBid() < 0) {
            throw new \InvalidArgumentException('Honor bid cannot be negative.');
        }

        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->playerRepository->get($message->getPlayerId(), $game);
        $player = $this->playerRepository->get($message->getTargetPlayerId(), $game);

        $this->stateManager->withState(
            $player,
            function (PlayerState $state) use ($message): PlayerState {
                if ($state->getHonorBid() === null) {
                    throw new NoHonorBidException();
                }

                return $state->setHonorBid($message->getBid());
            },
        );
    }
}
