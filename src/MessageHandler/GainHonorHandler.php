<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GainHonor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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
        $game = $this->gameRepository->get($message->getGameId(), 'choose_stronghold');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->stateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->setHonor($message->getHonorGain()),
        );
    }
}
