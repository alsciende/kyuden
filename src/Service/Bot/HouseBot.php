<?php

declare(strict_types=1);

namespace App\Service\Bot;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Api\StaleVersionException;
use App\Middleware\Stamp\ExpectedVersionStamp;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The bot of the platform (user HOUSE_BOT_ID), which plays the introductory games (POST /api/bot-games). It plays in
 * the request of the message that gave it something to do, once the response is sent (kernel.terminate): the player
 * gets the response at once and sees the moves of the bot through the notifications of the game.
 *
 * The games a message has changed are scheduled by HouseBotMiddleware. The bot plays its actions one at a time, through
 * the bus like any player, until it has nothing to do (BotStrategy). Before dispatching, the entity manager is cleared
 * so that GameLockMiddleware loads the game again, and the version the bot decided on is expected
 * (ExpectedVersionStamp): an action of the opponent in between makes the bot decide again.
 *
 * Mutable (the scheduled games of the request): reset between requests.
 */
final class HouseBot implements ResetInterface
{
    /**
     * At most this many actions per request, so that a strategy that never runs out of actions stops.
     */
    public const int MAX_ACTIONS = 100;

    /**
     * @var array<string, true> ids of the games changed during the request
     */
    private array $scheduledGameIds = [];

    public function __construct(
        private readonly string $houseBotId,
        private readonly BotStrategy $strategy,
        private readonly GameRepository $gameRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getUserId(): string
    {
        return $this->houseBotId;
    }

    public function schedule(string $gameId): void
    {
        $this->scheduledGameIds[$gameId] = true;
    }

    #[AsEventListener(KernelEvents::TERMINATE)]
    public function onTerminate(): void
    {
        $this->playScheduledGames();
    }

    /**
     * Plays in the games changed during the request, including by the bot itself.
     *
     * @return int the number of actions sent
     */
    public function playScheduledGames(): int
    {
        $actions = 0;
        while ($this->scheduledGameIds !== [] && $actions < self::MAX_ACTIONS) {
            $gameId = array_key_first($this->scheduledGameIds);
            unset($this->scheduledGameIds[$gameId]);
            $actions += $this->play($gameId, self::MAX_ACTIONS - $actions);
        }
        $this->scheduledGameIds = [];

        return $actions;
    }

    /**
     * Plays the house bot in a game until it has nothing to do.
     *
     * @return int the number of actions sent
     */
    public function play(string $gameId, int $maxActions = self::MAX_ACTIONS): int
    {
        $actions = 0;
        while ($actions < $maxActions) {
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

                return $actions;
            }
        }

        return $actions;
    }

    private function findPlayer(Game $game): ?Player
    {
        return $game->getPlayers()->findFirst(fn (int $key, Player $player): bool => $player->getUserId() === $this->houseBotId);
    }

    #[\Override]
    public function reset(): void
    {
        $this->scheduledGameIds = [];
    }
}
