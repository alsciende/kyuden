<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\ImminentTriggerCancelledEvent;
use App\Exception\Rules\NoInterruptWindowException;
use App\Message\CancelImminentTrigger;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\Interrupt\InterruptWindowService;
use App\State\Model\InterruptWindow;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted from any player of the game while an interrupt window is open: the engine does not apply card effects,
 * which decide what is cancelled. A window left without condition closes without doing what waited in it
 * (InterruptWindowMiddleware).
 */
#[AsMessageHandler]
final readonly class CancelImminentTriggerHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private InterruptWindowService $interruptWindowService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        if (! $this->interruptWindowService->current($game) instanceof InterruptWindow) {
            throw new NoInterruptWindowException();
        }
    }

    public function __invoke(CancelImminentTrigger $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->interruptWindowService->cancel($game, $message->getCardId(), $message->getRing());
        $this->eventDispatcher->dispatch(new ImminentTriggerCancelledEvent($game, $player));
    }
}
