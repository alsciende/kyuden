<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deck;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Enum\TournamentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TournamentEntry>
 */
class TournamentEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TournamentEntry::class);
    }

    /**
     * The players registered in the tournament, in their order of registration, with their users.
     *
     * @return list<TournamentEntry>
     */
    public function findByTournament(Tournament $tournament): array
    {
        /** @var list<TournamentEntry> $entries */
        $entries = $this->createQueryBuilder('e')
            ->addSelect('u')
            ->join('e.user', 'u')
            ->where('e.tournament = :tournament')
            ->setParameter('tournament', $tournament)
            ->orderBy('e.registeredAt', \SortDirection::Ascending)
            ->addOrderBy('e.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $entries;
    }

    /**
     * The number of the players registered in each tournament, in one query.
     *
     * @param list<Tournament> $tournaments
     *
     * @return array<string, int> by id of tournament (none for a tournament without players)
     */
    public function countByTournament(array $tournaments): array
    {
        if ($tournaments === []) {
            return [];
        }

        /** @var list<array{id: string, entrants: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.tournament) AS id', 'COUNT(e.id) AS entrants')
            ->where('e.tournament IN (:tournaments)')
            ->setParameter('tournaments', $tournaments)
            ->groupBy('e.tournament')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $row): array => ['id' => $row['id'], 'entrants' => (int) $row['entrants']], $rows), 'entrants', 'id');
    }

    /**
     * The registrations of the user in the tournaments that have not ended (cancelled ones included), the first to
     * begin first, with their tournaments and decks.
     *
     * @return list<TournamentEntry>
     */
    public function findNotEndedOfUser(User $user): array
    {
        /** @var list<TournamentEntry> $entries */
        $entries = $this->createQueryBuilder('e')
            ->addSelect('t', 'd')
            ->join('e.tournament', 't')
            ->join('e.deck', 'd')
            ->where('e.user = :user')
            ->andWhere('t.status != :ended')
            ->setParameter('user', $user)
            ->setParameter('ended', TournamentStatus::Ended)
            ->orderBy('t.startsAt', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $entries;
    }

    /**
     * Whether the deck is registered in a tournament still to be played (neither ended nor cancelled).
     */
    public function isDeckInUpcomingTournament(Deck $deck): bool
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->join('e.tournament', 't')
            ->where('e.deck = :deck')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('deck', $deck)
            ->setParameter('statuses', TournamentStatus::upcomingOrInProgress())
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function isDeckRegistered(Deck $deck): bool
    {
        return $this->count(['deck' => $deck]) > 0;
    }

    /**
     * Unregisters the user from the tournaments they can still leave: those cancelled, and those open that do not
     * lock before the time given (Tournament::isOpenAt()).
     */
    public function deleteLeavableOfUser(User $user, \DateTimeImmutable $now): void
    {
        $leavable = 'SELECT t.id FROM ' . Tournament::class . ' t WHERE t.status = :cancelled OR (t.status = :open AND t.startsAt > :lockedFrom)';
        $this->getEntityManager()->createQuery('DELETE FROM ' . TournamentEntry::class . " e WHERE e.user = :user AND e.tournament IN ({$leavable})")
            ->setParameter('user', $user)
            ->setParameter('cancelled', TournamentStatus::Cancelled)
            ->setParameter('open', TournamentStatus::Open)
            // open at that time: it locks one hour before it begins
            ->setParameter('lockedFrom', $now->modify('+' . Tournament::LOCK_LEAD))
            ->execute();
    }
}
