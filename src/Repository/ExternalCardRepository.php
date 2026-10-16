<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExternalCard;
use App\Enum\CardSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExternalCard>
 */
class ExternalCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalCard::class);
    }

    /**
     * The codes of the cards linked to external ids of a source (a single query).
     *
     * @param list<string> $externalIds
     *
     * @return array<string, string> card code by external id, for the external ids that are known
     */
    public function findCardCodes(CardSource $source, array $externalIds): array
    {
        if ($externalIds === []) {
            return [];
        }

        /** @var list<array{externalId: string, code: string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('e.externalId', 'IDENTITY(e.card) AS code')
            ->where('e.source = :source')
            ->andWhere('e.externalId IN (:externalIds)')
            ->setParameter('source', $source)
            ->setParameter('externalIds', $externalIds)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'code', 'externalId');
    }
}
