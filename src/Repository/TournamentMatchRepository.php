<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Game;
use App\Entity\Tournament;
use App\Entity\TournamentMatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TournamentMatch>
 */
class TournamentMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TournamentMatch::class);
    }

    /**
     * The matches of a round, by table.
     *
     * @return list<TournamentMatch>
     */
    public function findByRound(Tournament $tournament, int $round): array
    {
        return $this->findBy(['tournament' => $tournament, 'round' => $round], ['table' => 'ASC']);
    }

    /**
     * The matches of the rounds that have begun (up to the current one), by round and table, with their players.
     *
     * @return list<TournamentMatch>
     */
    public function findRevealed(Tournament $tournament): array
    {
        /** @var list<TournamentMatch> $matches */
        $matches = $this->createQueryBuilder('m')
            ->addSelect('f', 'fu', 's', 'su')
            ->join('m.first', 'f')
            ->join('f.user', 'fu')
            ->leftJoin('m.second', 's')
            ->leftJoin('s.user', 'su')
            ->where('m.tournament = :tournament')
            ->andWhere('m.round <= :round')
            ->setParameter('tournament', $tournament)
            ->setParameter('round', $tournament->getCurrentRound())
            ->orderBy('m.round', \SortDirection::Ascending)
            ->addOrderBy('m.table', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $matches;
    }

    public function findOneByGame(Game $game): ?TournamentMatch
    {
        return $this->findOneBy(['game' => $game]);
    }
}
