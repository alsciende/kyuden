<?php

declare(strict_types=1);

namespace App\Service\Abandonment;

use App\Exception\Api\StaleVersionException;
use App\Exception\Rules\GameEndedException;
use App\Message\EndAbandonedGame;
use App\Middleware\Stamp\ExpectedVersionStamp;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Ends the games every player has left (EndAbandonedGame, nobody wins): unchanged for LEFT_AFTER seconds, and read by
 * none of their players for as long. Both players of a game gone, or the player of a game of the house bot (which never
 * reads the game, nor ends it): nobody else would end them. A player left alone ends the game sooner, and wins
 * (AbandonmentClaimer). Run by the worker (the task EndAbandonedGames, on the schedule of the application).
 */
final readonly class AbandonedGamesEnder
{
    public const int LEFT_AFTER = 3600;

    public function __construct(
        private GameRepository $gameRepository,
        private MessageBusInterface $bus,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int the number of games ended
     */
    public function endAll(): int
    {
        $games = $this->gameRepository->findLeftSince($this->clock->now()->modify('-' . self::LEFT_AFTER . ' seconds'));
        $ended = 0;
        foreach ($games as ['id' => $gameId, 'version' => $version]) {
            try {
                // the version read with the game: a player acting meanwhile keeps it in progress
                $this->entityManager->clear();
                $this->bus->dispatch(new EndAbandonedGame($gameId), [new ExpectedVersionStamp($version)]);
                ++$ended;
            } catch (StaleVersionException|GameEndedException) {
            } catch (\Exception $exception) {
                $this->logger->error('Failed to end the abandoned game {game}: {message}', [
                    'game' => $gameId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
            }
        }

        return $ended;
    }
}
