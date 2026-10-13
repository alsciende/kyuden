<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DeclareGloryCount;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Players declare their glory count in any order, and can declare it again until every player has declared theirs:
 * the glory count is then resolved (`glory_counted`).
 */
#[AsMessageHandler]
final readonly class DeclareGloryCountHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
    ) {
    }

    public function __invoke(DeclareGloryCount $message): void
    {
        if ($message->getGlory() < 0) {
            throw new \InvalidArgumentException('Glory cannot be negative.');
        }

        $game = $this->gameRepository->get($message->getGameId(), 'glory_count');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);

        $this->stateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->setGloryCount($message->getGlory()),
        );
    }
}
