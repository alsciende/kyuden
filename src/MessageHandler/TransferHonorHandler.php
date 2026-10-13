<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TransferHonor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place: the engine does not apply card effects, which decide who transfers honor and when.
 */
#[AsMessageHandler]
final readonly class TransferHonorHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(TransferHonor $message): void
    {
        if ($message->getAmount() < 0) {
            throw new \InvalidArgumentException('Transferred honor cannot be negative.');
        }

        if ($message->getPlayerId() === $message->getRecipientId()) {
            throw new \InvalidArgumentException('A player cannot transfer honor to themselves.');
        }

        $game = $this->gameRepository->get($message->getGameId());
        $giver = $this->playerRepository->get($message->getPlayerId(), $game);
        $recipient = $this->playerRepository->get($message->getRecipientId(), $game);
        $this->stateManager->withState(
            $giver,
            fn (PlayerState $state): PlayerState => $state->decrementHonor($message->getAmount()),
        );
        $this->stateManager->withState(
            $recipient,
            fn (PlayerState $state): PlayerState => $state->incrementHonor($message->getAmount()),
        );
    }
}
