<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
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
 * Also allowed during a duel: no action window is open (D.9), but interrupts and reactions can be triggered (e.g. Stay
 * Your Hand, Insult to Injury), and the engine cannot tell them from action events.
 */
#[AsMessageHandler]
final readonly class PlayEventHandler implements PlayerActionHandlerInterface
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

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        if (! $this->opportunityService->getPassingRule($game) instanceof PassingRule) {
            throw new NotInOpportunitiesStepException($game->getCurrentPlace());
        }

        if (! $this->gameStateManager->getState($game)->isActivePlayer($player)) {
            throw new NotActivePlayerException();
        }
    }

    public function __invoke(PlayEvent $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->handService->assertPlayable($card, $player, Type::Event);

        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->spendFate($message->getCardCost()),
        );
        $this->conflictCardStateMachine->apply($card, 'discard_card');
    }
}
