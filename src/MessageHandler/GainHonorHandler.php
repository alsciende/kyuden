<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GainHonor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place: the engine does not apply card effects, which decide who gains honor and when.
 */
#[AsMessageHandler]
final readonly class GainHonorHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(GainHonor $message): void
    {
        if ($message->getHonorGain() < 0) {
            throw new \InvalidArgumentException('Gained honor cannot be negative.');
        }

        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->stateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->incrementHonor($message->getHonorGain()),
        );
    }
}
