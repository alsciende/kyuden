<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deck;
use App\Entity\Player;
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
     * The decks a user can use: their own decks not deleted and the decks without owner, by id.
     *
     * @return list<Deck>
     */
    public function findUsableBy(User $user): array
    {
        /** @var list<Deck> $decks */
        $decks = $this->createQueryBuilder('d')
            ->where('d.owner IS NULL OR d.owner = :user')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('d.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $decks;
    }

    /**
     * A deck the user can use, or null when it does not exist, belongs to another user or was deleted.
     */
    public function findUsable(string $id, User $user): ?Deck
    {
        $deck = $this->find($id);

        return $deck instanceof Deck && $deck->isUsableBy($user) ? $deck : null;
    }

    /**
     * A deck of the user with these cards (Deck::hashCards()), one not deleted first.
     */
    public function findOwnedWithContent(User $owner, string $contentHash): ?Deck
    {
        $decks = $this->findBy(['owner' => $owner, 'contentHash' => $contentHash], ['id' => 'ASC']);
        usort($decks, fn (Deck $a, Deck $b): int => $a->isDeleted() <=> $b->isDeleted());

        return $decks[0] ?? null;
    }

    /**
     * Whether a player of a game plays the deck.
     */
    public function isPlayed(Deck $deck): bool
    {
        return $this->getEntityManager()->getRepository(Player::class)->count(['deck' => $deck]) > 0;
    }

    /**
     * @return list<Deck> the decks every user can use (the starter decks), by id
     */
    public function findWithoutOwner(): array
    {
        return $this->findBy(['owner' => null], ['id' => 'ASC']);
    }

    /**
     * Deletes the decks of a user, but those played in a game (the players keep their deck), which only lose their
     * name. The user must have left the queue.
     */
    public function deleteByOwner(User $owner): void
    {
        $played = 'SELECT IDENTITY(p.deck) FROM ' . Player::class . ' p';
        $this->getEntityManager()->createQuery('DELETE FROM ' . Deck::class . " d WHERE d.owner = :owner AND d.id NOT IN ({$played})")
            ->setParameter('owner', $owner)
            ->execute();
        $this->getEntityManager()->createQuery('UPDATE ' . Deck::class . ' d SET d.name = NULL WHERE d.owner = :owner')
            ->setParameter('owner', $owner)
            ->execute();
    }
}
