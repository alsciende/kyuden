<?php

declare(strict_types=1);

namespace App\Service\Deck;

use App\Entity\Deck;
use App\Entity\User;
use App\Enum\DeckImportOutcome;
use App\Exception\Api\InvalidDeckException;
use App\Model\ImportedDeck;
use App\Repository\DeckRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Uid\Uuid;

/**
 * Creates the deck of a user from its cards in one of the formats (DeckFormatInterface): the cards are converted to
 * their codes, then the deck is checked against the deckbuilding rules (DeckConstructionValidator). A user who has a
 * deck with the same cards keeps it, restored if they had deleted it. The caller flushes.
 */
readonly class DeckImporter
{
    /**
     * @param iterable<DeckFormatInterface> $formats
     */
    public function __construct(
        #[AutowireIterator(DeckFormatInterface::class)]
        private iterable $formats,
        private DeckConstructionValidator $constructionValidator,
        private DeckRepository $deckRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, int> $cards quantity by card id of the format
     *
     * @throws InvalidDeckException
     */
    public function import(User $owner, ?string $name, string $formatName, array $cards): ImportedDeck
    {
        $format = $this->getFormat($formatName);
        if (! $format instanceof DeckFormatInterface) {
            throw new InvalidDeckException([[
                'property_path' => 'format',
                'message' => sprintf('Unknown format, expected one of: %s.', implode(', ', $this->getFormatNames())),
            ]]);
        }

        $converted = $format->toCodes($cards);
        if ($converted['unknown'] !== []) {
            throw new InvalidDeckException(array_map(
                fn (string $id): array => ['property_path' => "cards[{$id}]", 'message' => 'Unknown card.'],
                $converted['unknown'],
            ));
        }

        $errors = $this->constructionValidator->validate($converted['cards']);
        if ($errors !== []) {
            throw new InvalidDeckException(array_map(
                fn (string $error): array => ['property_path' => 'cards', 'message' => $error],
                $errors,
            ));
        }

        $owned = $this->deckRepository->findOwnedWithContent($owner, Deck::hashCards($converted['cards']));
        if ($owned instanceof Deck && ! $owned->isDeleted()) {
            return new ImportedDeck($owned, DeckImportOutcome::AlreadyOwned);
        }
        if ($owned instanceof Deck) {
            $owned->restore();
            $owned->setName($name);

            return new ImportedDeck($owned, DeckImportOutcome::Restored);
        }

        $deck = new Deck(Uuid::v4()->toString());
        $deck->setOwner($owner);
        $deck->setName($name);
        $deck->setCards($converted['cards']);
        $this->entityManager->persist($deck);

        return new ImportedDeck($deck, DeckImportOutcome::Created);
    }

    /**
     * @return list<string>
     */
    public function getFormatNames(): array
    {
        $names = [];
        foreach ($this->formats as $format) {
            $names[] = $format->getName();
        }
        sort($names);

        return $names;
    }

    private function getFormat(string $name): ?DeckFormatInterface
    {
        foreach ($this->formats as $format) {
            if ($format->getName() === $name) {
                return $format;
            }
        }

        return null;
    }
}
