<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\GloryCountDeclaredEvent;
use App\Message\DeclareGloryCount;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Players declare their glory count in any order, and can declare it again until every player has declared theirs:
 * the glory count is then resolved (`glory_counted`).
 */
#[AsMessageHandler]
final readonly class DeclareGloryCountHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        $game->assertPlace('glory_count');
    }

    public function __invoke(DeclareGloryCount $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        $this->assertAvailable($game, $player);

        $this->stateManager->withState(
            $player,
            fn (PlayerState $state): PlayerState => $state->setGloryCount($message->getGlory()),
        );
        $this->eventDispatcher->dispatch(new GloryCountDeclaredEvent($game, $player, $message->getGlory()));
    }
}
