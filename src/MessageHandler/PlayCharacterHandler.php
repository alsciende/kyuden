<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Type;
use App\Event\CharacterPlayedIntoConflictEvent;
use App\Event\FatePlacedOnCardEvent;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\NotInConflictException;
use App\Message\PlayCharacter;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\OpportunityService;
use App\Service\PlayService;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Model\CardState;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Allowed in the action windows where characters can be played from hand, whether the character is in a hand or
 * elsewhere (PlayService: the effect allowing it is not checked). The player pays the cost of the character, which
 * enters play ready under their control, at home or participating in the conflict on their side, then places fate on
 * it; a province it leaves is refilled once the action is ended, if still empty. The player keeps the opportunity to
 * act and ends their action (EndResolution).
 */
#[AsMessageHandler]
final readonly class PlayCharacterHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private GameStateManager $gameStateManager,
        private CardStateManager $cardStateManager,
        private OpportunityService $opportunityService,
        private PlayService $playService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $this->opportunityService->assertCanTakeAction($game, $player, playFromHand: true);
    }

    public function __invoke(PlayCharacter $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->playService->assertPlayable($card, Type::Character);

        if ($message->isIntoConflict()) {
            $conflict = $this->gameStateManager->getState($game)->conflict ?? throw new NoConflictException();
            if (! in_array($player->getId(), [$conflict->attackingPlayerId, $conflict->defendingPlayerId], true)) {
                throw new NotInConflictException();
            }
        }

        $this->playService->payCost($player, $message->getCardCost() + $message->getFateCost());
        $this->playService->enterPlay($card, $player);
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
