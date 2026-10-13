<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\Rules\NotActivePlayerException;
use App\Message\PassConflict;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Uses one conflict opportunity with no type: the player can still declare a conflict of a type not declared yet if
 * they have an opportunity left. The action window of the conflict phase opens again (3.3).
 */
#[AsMessageHandler]
final readonly class PassConflictHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private WorkflowInterface $gameStateMachine,
    ) {
    }

    public function __invoke(PassConflict $message): void
    {
        $game = $this->gameRepository->get($message->getGameId(), 'declare_conflict');
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }

        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->useConflictOpportunity(null),
        );
        $this->gameStateManager->withState(
            $game,
            function (GameState $state) use ($player): GameState {
                $state->lastConflictPlayerId = $player->getId();

                return $state;
            },
        );

        $this->gameStateMachine->apply($game, 'conflict_passed');
    }
}
