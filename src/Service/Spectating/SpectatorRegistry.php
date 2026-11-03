<?php

declare(strict_types=1);

namespace App\Service\Spectating;

use App\Entity\Game;
use App\Entity\Spectator;
use App\Entity\User;
use App\Notification\SpectatorsUpdatedNotification;
use App\Notifier\NotifierInterface;
use App\Repository\SpectatorRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Who watches a game. An account watches a game it reads without playing it (its view or its log), until it leaves or
 * has not read it for TIMEOUT seconds: the web client reads a game at least every 15 seconds, the long-poll of its log
 * lasts 30 seconds at most. A spectator who has stopped reading is removed when anybody reads the game or its
 * spectators; the spectators of the games nobody reads any more (ended, abandoned) are removed after PURGE_AFTER, when
 * a spectator reads a game. Every arrival and departure is told to the players and the spectators
 * (SpectatorsUpdatedNotification), who read the spectators again; nothing of it is written in the game (no version,
 * no log).
 */
final readonly class SpectatorRegistry
{
    public const int TIMEOUT = 45;

    /** seconds after which a spectator of any game is removed, even if nobody reads that game any more */
    public const int PURGE_AFTER = 3600;

    public function __construct(
        private SpectatorRepository $spectatorRepository,
        private NotifierInterface $notifier,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The user reads a game they do not play: they watch it, until it ends.
     */
    public function see(Game $game, User $user): void
    {
        $now = $this->clock->now();
        $this->spectatorRepository->purge($now->modify('-' . self::PURGE_AFTER . ' seconds'));
        if ($game->getCurrentPlace() === Game::STATE_ENDED) {
            $this->prune($game);

            return;
        }

        $pruned = $this->spectatorRepository->prune($game->getId(), $this->expiry());
        $arrived = $this->spectatorRepository->see($game->getId(), $user->getId(), $now);
        if ($pruned > 0 || $arrived) {
            $this->notify($game);
        }
    }

    /**
     * Somebody reads the game: the spectators who have stopped reading it are removed.
     */
    public function prune(Game $game): void
    {
        if ($this->spectatorRepository->prune($game->getId(), $this->expiry()) > 0) {
            $this->notify($game);
        }
    }

    /**
     * The user stops watching the game.
     */
    public function leave(Game $game, User $user): void
    {
        if ($this->spectatorRepository->leave($game->getId(), $user->getId())) {
            $this->notify($game);
        }
    }

    /**
     * The users watching a game, in their order of arrival.
     *
     * @return list<User>
     */
    public function getSpectators(Game $game): array
    {
        return array_map(
            fn (Spectator $spectator): User => $spectator->getUser(),
            $this->spectatorRepository->findSeenSince($game->getId(), $this->expiry()),
        );
    }

    /**
     * @return list<string> the ids of the users watching a game
     */
    public function getSpectatorIds(Game $game): array
    {
        return array_map(fn (User $user): string => $user->getId(), $this->getSpectators($game));
    }

    /**
     * The number of spectators of each game, by id of the game.
     *
     * @param list<string> $gameIds
     *
     * @return array<string, int>
     */
    public function count(array $gameIds): array
    {
        return $this->spectatorRepository->countSeenSince($gameIds, $this->expiry());
    }

    private function expiry(): \DateTimeImmutable
    {
        return $this->clock->now()->modify('-' . self::TIMEOUT . ' seconds');
    }

    private function notify(Game $game): void
    {
        $this->notifier->notify(new SpectatorsUpdatedNotification($game, $this->getSpectatorIds($game)));
    }
}
