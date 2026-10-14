<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
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
final readonly class TransferHonorHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(TransferHonor $message): void
    {
        if ($message->getGiverId() === $message->getRecipientId()) {
            throw new \InvalidArgumentException('A player cannot transfer honor to themselves.');
        }

        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $giver = $this->playerRepository->get($message->getGiverId(), $game);
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
