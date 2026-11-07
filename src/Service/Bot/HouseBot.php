<?php

declare(strict_types=1);

namespace App\Service\Bot;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Api\StaleVersionException;
use App\Message\ConcedeGame;
use App\Message\DeclareNoDefenders;
use App\Message\EndResolution;
use App\Message\PassConflict;
use App\Message\PassOpportunity;
use App\Middleware\Stamp\ExpectedVersionStamp;
use App\Repository\GameRepository;
use App\Service\AvailableActionsResolver;
use App\Task\PlayHouseBotTurn;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The bot of the platform (user HOUSE_BOT_ID), which plays the introductory games (POST /api/bot-games). It plays in
 * the worker, out of the requests: once a message has changed a game it plays (HouseBotMiddleware), its turn is sent to
 * the worker (PlayHouseBotTurn), which plays it at once (the transport is woken by LISTEN/NOTIFY). The player gets the
 * response at once and sees the moves of the bot through the notifications of the game.
 *
 * The bot plays its actions one at a time, through the bus like any player, until it has nothing to do (BotStrategy).
 * Before dispatching, the entity manager is cleared so that GameLockMiddleware loads the game again, and the version the
 * bot decided on is expected (ExpectedVersionStamp): an action of the opponent in between makes the bot decide again.
 * Its own actions send no turn: it plays until it has nothing to do.
 *
 * Its opponent never waits for it in vain: a turn waits in the transport while the worker is stopped, and a worker
 * killed while playing it plays it again once started. A failure of the bot (a bug of the strategy, an action the
 * engine refuses) makes it do nothing in the step if it can (pass), else concede: the reason of the concession is the
 * failure, for the developers only.
 *
 * Mutable (the game it is playing): reset between the messages of the worker.
 */
final class HouseBot implements ResetInterface
{
    /**
     * At most this many actions per turn, so that a strategy that never runs out of actions stops.
     */
    public const int MAX_ACTIONS = 100;

    /**
     * What the bot does after a failure, when available, in this order: nothing in the step.
     */
    private const array FALLBACK_ACTIONS = ['pass_opportunity', 'end_resolution', 'pass_conflict', 'declare_no_defenders'];

    /**
     * The game the bot is playing, whose changes send no turn.
     */
    private ?string $playingGameId = null;

    public function __construct(
        private readonly string $houseBotId,
        private readonly BotStrategy $strategy,
        private readonly GameRepository $gameRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ManagerRegistry $managerRegistry,
        private readonly AvailableActionsResolver $availableActionsResolver,
        private readonly MessageBusInterface $bus,
        private readonly MessageBusInterface $taskBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getUserId(): string
    {
        return $this->houseBotId;
    }

    /**
     * A message has changed the game: sends the turn of the bot to the worker if it plays the game, which has not ended,
     * and is not playing it already. The game is the one the message has just loaded (the identity map): no query.
     *
     * Every message sends its turn, none is skipped as a duplicate: the worker may play the turn of a first message
     * before a second one of the same request is handled.
     */
    public function wake(string $gameId): void
    {
        if ($gameId === $this->playingGameId) {
            return;
        }

        $game = $this->gameRepository->find($gameId);
        if ($game instanceof Game && $game->getCurrentPlace() !== Game::STATE_ENDED && $this->findPlayer($game) instanceof Player) {
            $this->taskBus->dispatch(new PlayHouseBotTurn($gameId));
        }
    }

    /**
     * Plays the house bot in a game until it has nothing to do.
     *
     * @return int the number of actions sent
     */
    public function play(string $gameId): int
    {
        $this->playingGameId = $gameId;
        try {
            return $this->playUntilDone($gameId);
        } finally {
            $this->playingGameId = null;
        }
    }

    private function playUntilDone(string $gameId): int
    {
        $actions = 0;
        while ($actions < self::MAX_ACTIONS) {
            $game = $this->gameRepository->find($gameId);
            $player = $game instanceof Game ? $this->findPlayer($game) : null;
            if (! $game instanceof Game || ! $player instanceof Player) {
                return $actions;
            }

            try {
                $message = $this->strategy->nextAction($game, $player);
                if ($message === null) {
                    return $actions;
                }

                $version = $game->getVersion();
                $this->entityManager->clear();
                $this->bus->dispatch($message, [new ExpectedVersionStamp($version)]);
                ++$actions;
            } catch (StaleVersionException) {
                // the opponent has acted since the bot decided: it decides again
                $this->entityManager->clear();
            } catch (\Exception $exception) {
                $this->logger->error('The house bot failed to play in game {game}: {message}', [
                    'game' => $gameId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
                $sent = $this->recover($gameId, $exception);
                if ($sent === null) {
                    return $actions;
                }
                $actions += $sent;
            }
        }

        return $actions;
    }

    /**
     * After a failure, the bot does nothing in the step if it can (FALLBACK_ACTIONS), else concedes, so that its
     * opponent is not left waiting for it.
     *
     * @return int|null the number of actions sent (0: the opponent has acted meanwhile, the bot decides again), null
     *                  when the bot cannot even concede
     */
    private function recover(string $gameId, \Exception $failure): ?int
    {
        $this->resetEntityManager();
        $game = $this->gameRepository->find($gameId);
        $player = $game instanceof Game ? $this->findPlayer($game) : null;
        if (! $game instanceof Game || ! $player instanceof Player) {
            return null;
        }

        try {
            $available = $this->availableActionsResolver->resolve($game, $player);
        } catch (\Exception) {
            // the bot concedes
            $available = [];
        }
        $playerId = $player->getId();
        $messages = [];
        foreach (self::FALLBACK_ACTIONS as $type) {
            if (in_array($type, $available, true)) {
                $messages[] = match ($type) {
                    'pass_opportunity' => new PassOpportunity($gameId, $playerId),
                    'end_resolution' => new EndResolution($gameId, $playerId),
                    'pass_conflict' => new PassConflict($gameId, $playerId),
                    'declare_no_defenders' => new DeclareNoDefenders($gameId, $playerId),
                };
            }
        }
        $reason = mb_substr("The house bot has failed: {$failure->getMessage()}", 0, ConcedeGame::MAX_REASON_LENGTH);
        $messages[] = new ConcedeGame($gameId, $playerId, $reason);

        $version = $game->getVersion();
        foreach ($messages as $message) {
            try {
                $this->entityManager->clear();
                $this->bus->dispatch($message, [new ExpectedVersionStamp($version)]);

                return 1;
            } catch (StaleVersionException) {
                $this->entityManager->clear();

                return 0;
            } catch (\Exception $exception) {
                $this->logger->error('The house bot failed to recover in game {game}: {message}', [
                    'game' => $gameId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
                $this->resetEntityManager();
            }
        }

        return null;
    }

    /**
     * A failure during a flush closes the entity manager: the next actions need a new one.
     */
    private function resetEntityManager(): void
    {
        if ($this->entityManager->isOpen()) {
            $this->entityManager->clear();
        } else {
            $this->managerRegistry->resetManager();
        }
    }

    private function findPlayer(Game $game): ?Player
    {
        return $game->getPlayers()->findFirst(fn (int $key, Player $player): bool => $player->getUserId() === $this->houseBotId);
    }

    #[\Override]
    public function reset(): void
    {
        $this->playingGameId = null;
    }
}
