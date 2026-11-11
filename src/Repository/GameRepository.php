<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CardTypes\StrongholdCard;
use App\Entity\Game;
use App\Entity\Player;
use App\Enum\Clan;
use App\Exception\Data\GameNotFoundException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * @param string|list<string>|null $expectedPlace the place, or one of the places, the game must be in
     */
    public function get(string $gameId, string|array|null $expectedPlace = null): Game
    {
        $game = $this->find($gameId);

        if ($game === null) {
            throw new GameNotFoundException($gameId);
        }

        if ($expectedPlace !== null) {
            $game->assertPlace(...(array) $expectedPlace);
        }

        return $game;
    }

    /**
     * The game with its players, their stacks and the cards of the stacks with their logical cards, in a single query
     * (no lazy loading while building a view of the whole game).
     */
    public function findWithCards(string $gameId): ?Game
    {
        /** @var Game|null */
        return $this->createQueryBuilder('g')
            ->addSelect('p', 'u', 's', 'c', 'l')
            ->leftJoin('g.players', 'p')
            // the view names the players
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.stacks', 's')
            ->leftJoin('s.cards', 'c')
            // the available actions read the abilities of the cards in hand
            ->leftJoin('c.logicalCard', 'l')
            ->where('g.id = :id')
            ->setParameter('id', $gameId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The version of a game read from the database, not from the identity map: a message handled by another request
     * since the game was loaded is seen.
     */
    public function findVersion(string $gameId): ?int
    {
        $version = $this->getEntityManager()->getConnection()
            ->fetchOne('SELECT version FROM games WHERE id = :id', ['id' => $gameId]);

        return is_numeric($version) ? (int) $version : null;
    }

    /**
     * Whether a game has ended, read from the database without loading the game (GameLockMiddleware loads it when a
     * message is dispatched); null for an unknown game.
     */
    public function hasEnded(string $gameId): ?bool
    {
        $place = $this->getEntityManager()->getConnection()
            ->fetchOne('SELECT current_place FROM games WHERE id = :id', ['id' => $gameId]);

        return $place === false ? null : $place === Game::STATE_ENDED;
    }

    /**
     * The games a user plays or has played, with their players, the last joined first (a single query); not the
     * replays.
     *
     * @return list<Game>
     */
    public function findByUser(string $userId): array
    {
        /** @var list<Game> */
        return $this->createQueryBuilder('g')
            ->addSelect('p')
            ->join('g.players', 'mine', 'WITH', 'IDENTITY(mine.user) = :user')
            ->join('g.players', 'p')
            ->where('g.replayOf IS NULL')
            ->setParameter('user', $userId)
            ->orderBy('mine.joinDate', \SortDirection::Descending)
            ->addOrderBy('g.id', \SortDirection::Ascending)
            ->addOrderBy('p.joinRank', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * Whether a user plays a game that has started and not ended (not a replay): they finish it before starting another
     * one.
     */
    public function hasInProgress(string $userId): bool
    {
        return $this->findInProgressPlayersOf($userId) !== [];
    }

    /**
     * The games a user plays that have started and not ended (not the replays), as the ids of the game and of their player: no game is
     * loaded, so that a message can then be dispatched on it (GameLockMiddleware).
     *
     * @return list<array{gameId: string, playerId: string}>
     */
    public function findInProgressPlayersOf(string $userId): array
    {
        /** @var list<array{gameId: string, playerId: string}> */
        return $this->createQueryBuilder('g')
            ->select('g.id AS gameId', 'p.id AS playerId')
            ->join('g.players', 'p', 'WITH', 'IDENTITY(p.user) = :user')
            ->where('g.currentPlace NOT IN (:places)')
            ->andWhere('g.replayOf IS NULL')
            ->setParameter('user', $userId)
            ->setParameter('places', ['join_game', Game::STATE_ENDED])
            ->orderBy('g.id', \SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * The games that have started and not ended, unchanged since the time given and read by none of their players since
     * (Player::lastSeenAt, never written for a player who never reads it: the house bot), as their id and version: no
     * game is loaded, so that a message can then be dispatched on it (GameLockMiddleware).
     *
     * @return list<array{id: string, version: int}>
     */
    public function findLeftSince(\DateTimeImmutable $since): array
    {
        $seen = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Player::class, 'seen')
            ->where('seen.game = g')
            ->andWhere('seen.lastSeenAt >= :since');

        /** @var list<array{id: string, version: int}> */
        return $this->createQueryBuilder('g')
            ->select('g.id', 'g.version')
            ->where('g.currentPlace NOT IN (:places)')
            ->andWhere('g.updatedAt < :since')
            ->andWhere('NOT EXISTS (' . $seen->getDQL() . ')')
            // a replay nobody reads is deleted (findStaleReplayIds())
            ->andWhere('g.replayOf IS NULL')
            ->setParameter('places', ['join_game', Game::STATE_ENDED])
            ->setParameter('since', $since, 'datetime_immutable')
            ->orderBy('g.id', \SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * The games that have started and not ended, with their players and their users, in a single query; the last
     * started first (the latest arrival of their players).
     *
     * @return list<Game>
     */
    public function findInProgress(): array
    {
        return $this->findStarted();
    }

    /**
     * The games that have started (not the replays), those that have ended too when asked, of every user or of the user
     * given, with their players and their users, in a single query; the last started first (the latest arrival of their players).
     *
     * @return list<Game>
     */
    public function findStarted(bool $ended = false, ?string $userId = null): array
    {
        $query = $this->createQueryBuilder('g')
            ->addSelect('p', 'u')
            ->join('g.players', 'p')
            ->join('p.user', 'u')
            ->where('g.currentPlace NOT IN (:places)')
            ->andWhere('g.replayOf IS NULL')
            ->setParameter('places', $ended ? ['join_game'] : ['join_game', Game::STATE_ENDED])
            ->orderBy('g.id', \SortDirection::Ascending)
            ->addOrderBy('p.joinRank', \SortDirection::Ascending);
        if ($userId !== null) {
            $plays = $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(Player::class, 'mine')
                ->where('mine.game = g')
                ->andWhere('IDENTITY(mine.user) = :user');
            $query->andWhere($query->expr()->exists($plays->getDQL()))->setParameter('user', $userId);
        }
        /** @var list<Game> $games */
        $games = $query->getQuery()->getResult();

        $startedAt = fn (Game $game): int => max(array_map(
            fn (Player $player): int => $player->getJoinDate()->getTimestamp(),
            $game->getPlayers()->getValues(),
        ) ?: [0]);
        usort($games, fn (Game $a, Game $b): int => $startedAt($b) <=> $startedAt($a));

        return $games;
    }

    /**
     * The clan of the stronghold of each player of the games, by id of the player (a single query, no card loaded).
     *
     * @param list<string> $gameIds
     *
     * @return array<string, Clan>
     */
    public function findStrongholdClans(array $gameIds): array
    {
        if ($gameIds === []) {
            return [];
        }

        /** @var list<array{playerId: string, clan: Clan|null}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(c.player) AS playerId', 'l.clan AS clan')
            ->from(StrongholdCard::class, 'c')
            ->join('c.logicalCard', 'l')
            ->where('c.game IN (:games)')
            ->setParameter('games', $gameIds)
            ->getQuery()
            ->getArrayResult();

        $clans = [];
        foreach ($rows as $row) {
            if ($row['clan'] instanceof Clan) {
                $clans[$row['playerId']] = $row['clan'];
            }
        }

        return $clans;
    }

    /**
     * The replay of a game that the user follows, if any.
     */
    public function findReplay(string $originalId, string $userId): ?Game
    {
        /** @var Game|null */
        return $this->createQueryBuilder('g')
            ->where('IDENTITY(g.replayOf) = :original')
            ->andWhere('IDENTITY(g.replayUser) = :user')
            ->setParameter('original', $originalId)
            ->setParameter('user', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The ids of the replays a user follows: deleted with their account.
     *
     * @return list<string>
     */
    public function findReplayIdsOf(string $userId): array
    {
        /** @var list<string> */
        return $this->createQueryBuilder('g')
            ->select('g.id')
            ->where('IDENTITY(g.replayUser) = :user')
            ->setParameter('user', $userId)
            ->orderBy('g.id', \SortDirection::Ascending)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * The ids of the replays their user has read for the last time before the time given (Player::lastSeenAt of their
     * player in the replay), or never read and unchanged since.
     *
     * @return list<string>
     */
    public function findStaleReplayIds(\DateTimeImmutable $since): array
    {
        $read = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Player::class, 'reader')
            ->where('reader.game = g')
            ->andWhere('reader.user = g.replayUser')
            ->andWhere('reader.lastSeenAt >= :since');

        /** @var list<string> */
        return $this->createQueryBuilder('g')
            ->select('g.id')
            ->where('g.replayOf IS NOT NULL')
            ->andWhere('g.updatedAt IS NULL OR g.updatedAt < :since')
            ->andWhere('NOT EXISTS (' . $read->getDQL() . ')')
            ->setParameter('since', $since, 'datetime_immutable')
            ->orderBy('g.id', \SortDirection::Ascending)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Deletes a game and everything in it, in SQL (the cards and the stacks refer to each other): its cards (and the
     * stacks of the cards under them), its stacks, its players, then the game with its events, its log and its
     * spectators. The entities of the game already loaded are not detached.
     */
    public function delete(string $gameId): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $connection->transactional(function () use ($connection, $gameId): void {
            $connection->executeStatement('DELETE FROM physical_cards WHERE game_id = :game', ['game' => $gameId]);
            $connection->executeStatement('DELETE FROM stack WHERE player_id IN (SELECT id FROM players WHERE game_id = :game)', ['game' => $gameId]);
            $connection->executeStatement('DELETE FROM players WHERE game_id = :game', ['game' => $gameId]);
            $connection->executeStatement('DELETE FROM games WHERE id = :game', ['game' => $gameId]);
        });
    }
}
