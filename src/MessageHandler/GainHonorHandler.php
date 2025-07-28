<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GainHonor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\PlayerStateManager;
use App\State\PlayerState;
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
        $game = $this->gameRepository->get($message->getGameId(), 'place_provinces');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->stateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->setHonor($message->getHonorGain()),
        );
    }
}
