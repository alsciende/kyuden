<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\ImperialFavorDiscardedEvent;
use App\Exception\Rules\ImperialFavorUnclaimedException;
use App\Message\DiscardImperialFavor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, while a player has the Imperial Favor: the engine does
 * not apply card effects. The Imperial Favor returns to its unclaimed state, with no side (glossary, "Imperial Favor").
 */
#[AsMessageHandler]
final readonly class DiscardImperialFavorHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private GameStateManager $gameStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        if ($this->gameStateManager->getState($game)->imperialFavorHolderId === null) {
            throw new ImperialFavorUnclaimedException();
        }
    }

    public function __invoke(DiscardImperialFavor $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $holder = $game->getPlayer((string) $this->gameStateManager->getState($game)->imperialFavorHolderId);

        $this->gameStateManager->withState($game, function (GameState $state): GameState {
            $state->imperialFavorHolderId = null;
            $state->imperialFavorSide = null;

            return $state;
        });
        $this->eventDispatcher->dispatch(new ImperialFavorDiscardedEvent($game, $holder));
    }
}
