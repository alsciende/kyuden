<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\PassingRule;
use App\Enum\Type;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotInOpportunitiesStepException;
use App\Message\PlayEvent;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\HandService;
use App\Service\OpportunityService;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Allowed in any game place marked `opportunities` (action windows, reaction windows…) when the player has the
 * opportunity to act: the engine does not know the trigger of the event. The player pays its cost and the event goes
 * to the discard pile; the player resolves its effects with other actions, then ends their opportunity (EndOpportunity)
 * or passes.
 */
#[AsMessageHandler]
final readonly class PlayEventHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private OpportunityService $opportunityService,
        private HandService $handService,
        private WorkflowInterface $conflictCardStateMachine,
    ) {
    }

    public function __invoke(PlayEvent $message): void
    {
        if ($message->getCardCost() < 0) {
            throw new \InvalidArgumentException('Costs cannot be negative.');
        }

        $game = $this->gameRepository->get($message->getGameId());
        if (! $this->opportunityService->getPassingRule($game) instanceof PassingRule) {
            throw new NotInOpportunitiesStepException($game->getCurrentPlace());
        }

        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }

        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->handService->assertPlayable($card, $player, Type::Event);

        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->spendFate($message->getCardCost()),
        );
        $this->conflictCardStateMachine->apply($card, 'discard_card');
    }
}
