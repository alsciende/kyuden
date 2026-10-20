<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Type;
use App\Event\CharacterPlayedIntoConflictEvent;
use App\Event\FatePlacedOnCardEvent;
use App\Event\FateSpentEvent;
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
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Allowed in the action windows where characters can be played from hand. The player pays the cost of the character,
 * which enters play ready, at home or participating in the conflict on its controller's side, then places fate on it.
 * The player keeps the opportunity to act and ends their action (EndResolution).
 */
#[AsMessageHandler]
final readonly class PlayCharacterFromHandHandler implements PlayerActionHandlerInterface
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
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->opportunityService->assertCanTakeAction($game, $player, playFromHand: true);
    }

    public function __invoke(PlayCharacterFromHand $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
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
        if ($message->getCardCost() + $message->getFateCost() > 0) {
            $this->eventDispatcher->dispatch(new FateSpentEvent($game, $player, $message->getCardCost() + $message->getFateCost()));
        }
        $this->conflictCardStateMachine->apply($card, 'play_card');
        $this->cardStateManager->withState(
            $card,
            fn (CardState $state): CardState => $state->incrementFate($message->getFate()),
        );
        if ($message->getFate() > 0) {
            $this->eventDispatcher->dispatch(new FatePlacedOnCardEvent($game, $card, $message->getFate()));
        }

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
            $this->eventDispatcher->dispatch(new CharacterPlayedIntoConflictEvent($game, $card));
        }

        $this->opportunityService->takeAction($player);
    }
}
