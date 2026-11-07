<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Event\GameAbandonedEvent;
use App\Exception\Rules\GameNotAbandonedException;
use App\Exception\Rules\GameNotStartedException;
use App\Message\ClaimAbandonment;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Service\Abandonment\AwaitedPlayersResolver;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The players the game waits for have abandoned it: they are eliminated (VictoryService), and the automatic transition
 * `end_game` ends the game. Only what the state tells is checked here (the game waits for the opponents of the player,
 * not for the player), so that the events replay: how long it has waited is checked before the message is sent
 * (AbandonmentClaimer).
 */
#[AsMessageHandler]
final readonly class ClaimAbandonmentHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private AwaitedPlayersResolver $awaitedPlayersResolver,
        private PlayerStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ClaimAbandonment $message): void
    {
        $game = $this->gameRepository->get($message->getGameId());
        $player = $this->playerRepository->get($message->getPlayerId(), $game);
        if ($game->getCurrentPlace() === 'join_game') {
            throw new GameNotStartedException();
        }

        $awaited = $this->awaitedPlayersResolver->resolve($game);
        if ($awaited === [] || in_array($player, $awaited, true)) {
            throw new GameNotAbandonedException();
        }

        foreach ($awaited as $opponent) {
            $this->stateManager->withState($opponent, fn (PlayerState $state): PlayerState => $state->setAbandoned(true));
            $this->eventDispatcher->dispatch(new GameAbandonedEvent($game, $opponent));
        }
    }
}
