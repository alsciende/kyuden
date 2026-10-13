<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\Type;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\NotInConflictException;
use App\Message\PlayCharacterFromHand;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\HandService;
use App\Service\OpportunityService;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\CardState;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Allowed in the action windows where characters can be played from hand. The player pays the cost of the character,
 * which enters play ready, at home or participating in the conflict on its controller's side, then places fate on it.
 * The player keeps the opportunity to act and ends it (EndOpportunity).
 */
#[AsMessageHandler]
final readonly class PlayCharacterFromHandHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private CardStateManager $cardStateManager,
        private OpportunityService $opportunityService,
        private HandService $handService,
        private WorkflowInterface $conflictCardStateMachine,
    ) {
    }

    public function __invoke(PlayCharacterFromHand $message): void
    {
        if ($message->getCardCost() < 0 || $message->getFateCost() < 0 || $message->getFate() < 0) {
            throw new \InvalidArgumentException('Costs and fate cannot be negative.');
        }

        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->opportunityService->assertCanTakeAction($game, $player, playFromHand: true);
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->handService->assertPlayable($card, $player, Type::Character);

        if ($message->isIntoConflict()) {
            $conflict = $this->gameStateManager->getState($game)->conflict ?? throw new NoConflictException();
            if (! in_array($player->getId(), [$conflict->attackingPlayerId, $conflict->defendingPlayerId], true)) {
                throw new NotInConflictException();
            }
        }

        $this->playerStateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->spendFate($message->getCardCost() + $message->getFateCost()),
        );
        $this->conflictCardStateMachine->apply($card, 'play_card');
        $this->cardStateManager->withState(
            $card,
            fn (CardState $state): CardState => $state->incrementFate($message->getFate()),
        );

        if ($message->isIntoConflict()) {
            $this->gameStateManager->withState($game, function (GameState $state) use ($player, $card): GameState {
                $conflict = $state->currentConflict();
                if ($player->getId() === $conflict->attackingPlayerId) {
                    $conflict->attackerIds[] = $card->getId();
                } else {
                    $conflict->defenderIds[] = $card->getId();
                }

                return $state;
            });
        }
    }
}
