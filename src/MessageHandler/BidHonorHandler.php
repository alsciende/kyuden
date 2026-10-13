<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\HonorAlreadyBidException;
use App\Message\BidHonor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Players bid simultaneously: no player order, each player bids once per round.
 */
#[AsMessageHandler]
final readonly class BidHonorHandler
{
    public const int MINIMUM_BID = 1;
    public const int MAXIMUM_BID = 5;

    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(BidHonor $message): void
    {
        if ($message->getBid() < self::MINIMUM_BID || $message->getBid() > self::MAXIMUM_BID) {
            throw new \InvalidArgumentException(sprintf('Honor bid must be between %d and %d.', self::MINIMUM_BID, self::MAXIMUM_BID));
        }

        $game = $this->gameRepository->get($message->getGameId(), 'honor_bid');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);

        $this->stateManager->withState(
            $player,
            function (PlayerState $state) use ($message): PlayerState {
                if ($state->getHonorBid() !== null) {
                    throw new HonorAlreadyBidException();
                }

                return $state->setHonorBid($message->getBid());
            },
        );
    }
}
