<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\InterruptPassedEvent;
use App\Message\PassInterrupt;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\Interrupt\InterruptWindowService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted from a player the interrupt window open waits for, who resolves no interrupt initiated in it. The window
 * moves on once every player asked has passed (InterruptWindowMiddleware).
 */
#[AsMessageHandler]
final readonly class PassInterruptHandler implements PlayerActionHandlerInterface
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
        $this->interruptWindowService->assertCanPass($game, $player);
    }

    public function __invoke(PassInterrupt $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->interruptWindowService->pass($game, $player);
        $this->eventDispatcher->dispatch(new InterruptPassedEvent($game, $player));
    }
}
