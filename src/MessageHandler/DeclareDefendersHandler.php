<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Event\DefendersDeclaredEvent;
use App\Exception\Rules\CannotParticipateException;
use App\Exception\Rules\NotActivePlayerException;
use App\Message\DeclareDefenders;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Defenders are ready characters in play of the defending player (the only active player), not evaded by covert. The
 * conflict action window opens next.
 */
#[AsMessageHandler]
final readonly class DeclareDefendersHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private ConflictService $conflictService,
        private WorkflowInterface $gameStateMachine,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $game->assertPlace('declare_defenders');
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    public function __invoke(DeclareDefenders $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $state = $this->gameStateManager->getState($game);

        $conflict = $state->currentConflict();
        $defenderIds = array_values(array_unique($message->getDefenderIds()));
        foreach ($defenderIds as $defenderId) {
            $card = $this->cardRepository->get($defenderId, $game);
            $this->conflictService->assertCanParticipate($card, $player);
            if (in_array($defenderId, $conflict->evadedIds, true)) {
                throw new CannotParticipateException($card, 'it is evaded by covert');
            }
        }

        $this->gameStateManager->withState($game, function (GameState $state) use ($defenderIds): GameState {
            $state->currentConflict()->defenderIds = $defenderIds;

            return $state;
        });
        $this->eventDispatcher->dispatch(new DefendersDeclaredEvent($game, $player, array_map(fn (string $id): PhysicalCard => $this->cardRepository->get($id, $game), $defenderIds)));

        $this->gameStateMachine->apply($game, 'defenders_declared');
    }
}
