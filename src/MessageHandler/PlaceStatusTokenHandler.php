<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\StatusTokenPlacedEvent;
use App\Message\PlaceStatusToken;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\State\Manager\CardStateManager;
use App\State\Model\CardState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, on any character in play or province: the engine does
 * not apply card effects, which decide which token goes where and when (an honored status token on a province is
 * accepted too).
 */
#[AsMessageHandler]
final readonly class PlaceStatusTokenHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private ConflictService $conflictService,
        private CardStateManager $cardStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(PlaceStatusToken $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $card = $this->cardRepository->get($message->getCardId(), $game);
        $this->conflictService->assertCanHoldStatusToken($card);

        $status = $message->getToken()->toPersonalHonor();
        $result = $this->cardStateManager->getState($card)->receiveStatusToken($status)->getPersonalHonor();
        $this->cardStateManager->withState(
            $card,
            fn (CardState $state): CardState => $state->setPersonalHonor($result),
        );
        $this->eventDispatcher->dispatch(new StatusTokenPlacedEvent($game, $card, $status, $result));
    }
}
