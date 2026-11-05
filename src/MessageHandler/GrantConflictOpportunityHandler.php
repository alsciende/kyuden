<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Game;
use App\Entity\Player;
use App\Event\ConflictOpportunityGrantedEvent;
use App\Message\GrantConflictOpportunity;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Accepted in any game place and from any player of the game, for any player: the engine does not apply card effects
 * nor their limits ("Max 1 per phase"). The target gets one more conflict opportunity and may declare one more
 * conflict of the type, until the end of the round; the conflict phase goes on while a player has an opportunity left.
 */
#[AsMessageHandler]
final readonly class GrantConflictOpportunityHandler implements PlayerActionHandlerInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private PlayerStateManager $playerStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function assertAvailable(Game $game, Player $player): void
    {
        // accepted in any game place, from any player of the game
    }

    public function __invoke(GrantConflictOpportunity $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        // checks that the sender is a player of the game
        $this->assertAvailable($game, $this->playerRepository->get($message->getPlayerId(), $game));
        $target = $this->playerRepository->get($message->getTargetPlayerId(), $game);

        $this->playerStateManager->withState(
            $target,
            fn (PlayerState $state): PlayerState => $state->grantConflictOpportunity($message->getType()),
        );
        $this->eventDispatcher->dispatch(new ConflictOpportunityGrantedEvent($game, $target, $message->getType()));
    }
}
