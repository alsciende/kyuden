<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * The user signing in with a username, whatever its case.
     */
    public function findOneByUsername(string $username): ?User
    {
        return $this->findOneBy(['usernameCanonical' => User::canonicalizeUsername($username)]);
    }
}
