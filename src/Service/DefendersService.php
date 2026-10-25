<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\GameEventInterface;
use App\Exception\Rules\NotActivePlayerException;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The declaration of the defenders of a conflict (3.2.1), shared by DeclareDefenders and DeclareNoDefenders: the
 * defending player (the only active player) declares them, then the conflict action window opens.
 */
readonly class DefendersService
{
    public function __construct(
        private GameStateManager $gameStateManager,
        #[Target('game')]
        private WorkflowInterface $gameStateMachine,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function assertCanDeclare(Game $game, Player $player): void
    {
        $game->assertPlace('declare_defenders');
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    /**
     * Records the defenders, dispatches the event of the declaration and opens the conflict action window.
     *
     * @param list<string> $defenderIds
     */
    public function declare(Game $game, array $defenderIds, GameEventInterface $event): void
    {
        $this->gameStateManager->withState($game, function (GameState $state) use ($defenderIds): GameState {
            $state->currentConflict()->defenderIds = $defenderIds;

            return $state;
        });
        $this->eventDispatcher->dispatch($event);

        $this->gameStateMachine->apply($game, 'defenders_declared');
    }
}
