<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LinkedAccount;
use App\Entity\User;
use App\Enum\OAuthProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LinkedAccount>
 */
class LinkedAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LinkedAccount::class);
    }

    /**
     * The account linked to an identity of a provider, with its user (a single query).
     */
    public function findOneByIdentity(OAuthProvider $provider, string $subject): ?LinkedAccount
    {
        $linkedAccount = $this->createQueryBuilder('l')
            ->addSelect('u')
            ->join('l.user', 'u')
            ->where('l.provider = :provider')
            ->andWhere('l.subject = :subject')
            ->setParameter('provider', $provider)
            ->setParameter('subject', $subject)
            ->getQuery()
            ->getOneOrNullResult();
        \assert($linkedAccount === null || $linkedAccount instanceof LinkedAccount);

        return $linkedAccount;
    }

    public function findOneByUserAndProvider(User $user, OAuthProvider $provider): ?LinkedAccount
    {
        return $this->findOneBy(['user' => $user, 'provider' => $provider]);
    }

    /**
     * @return list<LinkedAccount> the identities linked to a user, by provider
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['provider' => 'ASC']);
    }

    /**
     * Unlinks every identity of a user.
     */
    public function deleteByUser(User $user): void
    {
        $this->createQueryBuilder('l')
            ->delete()
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
