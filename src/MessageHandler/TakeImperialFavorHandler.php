<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\ImperialFavorClaimedEvent;
use App\Message\TakeImperialFavor;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, for any player: the engine does not apply card effects.
 * The Imperial Favor is claimed from its unclaimed state or from a player, the target included (claimed again, it may
 * change side), and set to the side given (glossary, "Imperial Favor"). The game does not move on, unlike the claim of
 * the glory count (ClaimImperialFavor).
 */
#[AsMessageHandler]
final readonly class TakeImperialFavorHandler implements PlayerActionHandlerInterface
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
        // accepted in any game place, from any player of the game
    }

    public function __invoke(TakeImperialFavor $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $target = $this->playerRepository->get($message->getTargetPlayerId(), $game);

        $this->gameStateManager->withState($game, function (GameState $state) use ($target, $message): GameState {
            $state->imperialFavorHolderId = $target->getId();
            $state->imperialFavorSide = $message->getSide();

            return $state;
        });
        $this->eventDispatcher->dispatch(new ImperialFavorClaimedEvent($game, $target, $message->getSide()));
    }
}
