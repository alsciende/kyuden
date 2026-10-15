<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AccessToken;
use App\Entity\User;
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
}
