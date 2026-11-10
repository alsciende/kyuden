<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccessToken;
use App\Entity\User;
use App\Enum\AccessTokenType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccessToken>
 */
class AccessTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessToken::class);
    }

    /**
     * The access token matching a token sent by a client, with its user (a single query).
     */
    public function findOneByToken(#[\SensitiveParameter] string $token): ?AccessToken
    {
        $accessToken = $this->createQueryBuilder('t')
            ->addSelect('u')
            ->join('t.user', 'u')
            ->where('t.hash = :hash')
            ->setParameter('hash', AccessToken::hash($token))
            ->getQuery()
            ->getOneOrNullResult();
        \assert($accessToken === null || $accessToken instanceof AccessToken);

        return $accessToken;
    }

    /**
     * Revokes every access token of a user.
     *
     * @return int the number of tokens revoked
     */
    public function deleteByUser(User $user): int
    {
        $count = $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
        \assert(\is_int($count));

        return $count;
    }

    /**
     * The token is used now: written unless it was used after the threshold, so that frequent requests write seldom. A
     * single statement, the token in memory left as it is.
     */
    public function recordUse(AccessToken $token, \DateTimeImmutable $now, \DateTimeImmutable $threshold): void
    {
        $this->getEntityManager()->createQuery(
            'UPDATE ' . AccessToken::class . ' t SET t.lastUsedAt = :now WHERE t.id = :id AND t.lastUsedAt < :threshold',
        )
            ->setParameter('now', $now, 'datetime_immutable')
            ->setParameter('threshold', $threshold, 'datetime_immutable')
            ->setParameter('id', $token->getId())
            ->execute();
    }

    /**
     * Deletes the sessions of the accounts (not of the guests) last used before the time given.
     *
     * @return int the number of sessions deleted
     */
    public function deleteSessionsOfAccountsUnusedSince(\DateTimeImmutable $since): int
    {
        $count = $this->getEntityManager()->createQuery(
            'DELETE ' . AccessToken::class . ' t WHERE t.type = :session AND t.lastUsedAt < :since'
            . ' AND t.user IN (SELECT u.id FROM ' . User::class . ' u WHERE u.username IS NOT NULL)',
        )
            ->setParameter('session', AccessTokenType::Session)
            ->setParameter('since', $since, 'datetime_immutable')
            ->execute();
        \assert(\is_int($count));

        return $count;
    }
}
