<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\PersonalHonor;
use App\Event\StatusTokenMovedEvent;
use App\Exception\Rules\NoStatusTokenException;
use App\Exception\Rules\SameCharacterException;
use App\Message\MoveStatusToken;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\ConflictService;
use App\State\Manager\CardStateManager;
use App\State\Model\CardState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, on any characters in play: the engine does not apply
 * card effects, which decide whose token moves where and when.
 */
#[AsMessageHandler]
final readonly class MoveStatusTokenHandler implements PlayerActionHandlerInterface
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

    public function __invoke(MoveStatusToken $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $from = $this->cardRepository->get($message->getFromCardId(), $game);
        $to = $this->cardRepository->get($message->getToCardId(), $game);
        $this->conflictService->assertCharacterInPlay($from);
        $this->conflictService->assertCharacterInPlay($to);
        if ($from === $to) {
            throw new SameCharacterException($from);
        }

        $status = $this->cardStateManager->getState($from)->getPersonalHonor();
        if ($status === PersonalHonor::Ordinary) {
            throw new NoStatusTokenException();
        }

        // the target is checked before the source changes: nothing changes when it is rejected
        $result = $this->cardStateManager->getState($to)->receiveStatusToken($status)->getPersonalHonor();
        $this->cardStateManager->withState(
            $from,
            fn (CardState $state): CardState => $state->discardStatusToken(),
        );
        $this->cardStateManager->withState(
            $to,
            fn (CardState $state): CardState => $state->setPersonalHonor($result),
        );
        $this->eventDispatcher->dispatch(new StatusTokenMovedEvent($game, $from, $to, $status, $result));
    }
}
