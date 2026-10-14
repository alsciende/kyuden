<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\PassingRule;
use App\Event\OpportunityPassedEvent;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInOpportunitiesStepException;
use App\Message\PassOpportunity;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\OpportunityService;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Allowed in any game place marked `opportunities` in the game workflow. The player is no longer active; the other
 * players keep acting (in player order, or simultaneously) until every player has passed. In action windows
 * (PassingRule::Consecutive), a player who acts afterwards cancels the passes (EndOpportunity). The rules specific to a
 * step listen to OpportunityPassedEvent.
 */
#[AsMessageHandler]
final readonly class PassOpportunityHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $stateManager,
        private OpportunityService $opportunityService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        if (! $this->opportunityService->getPassingRule($game) instanceof PassingRule) {
            throw new NotInOpportunitiesStepException($game->getCurrentPlace());
        }

        $this->opportunityService->assertNoDuel($game);
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    public function __invoke(PassOpportunity $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $firstToPass = ! $this->hasAnyPlayerPassed($game);
        $this->stateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->setPassed(true),
        );
        $this->eventDispatcher->dispatch(new OpportunityPassedEvent($game, $player, $firstToPass));

        $this->opportunityService->pass($game, $player);
    }

    private function hasAnyPlayerPassed(Game $game): bool
    {
        return $game->getPlayers()->exists(
            fn (int $key, Player $player): bool => $this->stateManager->getState($player)->isPassed(),
        );
    }
}
