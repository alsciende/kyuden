<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deck;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deck>
 */
class DeckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deck::class);
    }

    /**
     * The decks a user can use: their own decks and the decks without owner, by id.
     *
     * @return list<Deck>
     */
    public function findUsableBy(User $user): array
    {
        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->where('d.owner IS NULL OR d.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('d.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * A deck the user can use, or null when it does not exist or belongs to another user.
     */
    public function findUsable(string $id, User $user): ?Deck
    {
        $deck = $this->find($id);

        return $deck instanceof Deck && $deck->isUsableBy($user) ? $deck : null;
    }

    /**
     * @return list<Deck> the decks every user can use (the starter decks), by id
     */
    public function findWithoutOwner(): array
    {
        return $this->findBy(['owner' => null], ['id' => \SortDirection::Ascending]);
    }
}
