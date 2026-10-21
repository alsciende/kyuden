<?php

declare(strict_types=1);

namespace App\Service\Deck;

use App\Entity\Deck;
use App\Entity\User;
use App\Enum\Clan;
use App\Exception\Data\DeckNotFoundException;
use App\Repository\DeckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Gives a user their own copy of the starter deck of a Great Clan, which they play and will change: the starter decks
 * are not theirs. The caller flushes.
 */
readonly class StarterDeckCopier
{
    public function __construct(
        private DeckRepository $deckRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws DeckNotFoundException when the starter deck of the clan is missing
     */
    public function copy(Clan $clan, User $owner): Deck
    {
        $starterDeckId = $clan->getStarterDeckId();
        $starterDeck = $this->deckRepository->find($starterDeckId);
        if (! $starterDeck instanceof Deck || $starterDeck->getOwner() instanceof User) {
            throw new DeckNotFoundException($starterDeckId);
        }

        $deck = new Deck(Uuid::v4()->toString());
        $deck->setOwner($owner);
        $deck->setName(sprintf('%s starter deck', ucfirst($clan->value)));
        $deck->setCards($starterDeck->getCards());
        $this->entityManager->persist($deck);

        return $deck;
    }
}
