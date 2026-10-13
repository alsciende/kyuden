<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\FateAlreadyCollectedException;
use App\Message\CollectFate;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CollectFateHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(CollectFate $message): void
    {
        if ($message->getAmount() < 0) {
            throw new \InvalidArgumentException('Collected fate cannot be negative.');
        }

        $game = $this->gameRepository->get($message->getGameId(), 'collect_fate');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->stateManager->withState(
            $player,
            function (PlayerState $state) use ($message): PlayerState {
                if ($state->isFateCollected()) {
                    throw new FateAlreadyCollectedException();
                }

                return $state->incrementFate($message->getAmount())->setFateCollected(true);
            },
        );
    }
}
