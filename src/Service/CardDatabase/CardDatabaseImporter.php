<?php

declare(strict_types=1);

namespace App\Service\CardDatabase;

use App\Entity\Cycle;
use App\Entity\Deck;
use App\Entity\ExternalCard;
use App\Entity\LogicalCard;
use App\Entity\Pack;
use App\Entity\Printing;
use App\Entity\User;
use App\Enum\CardSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Writes the database of the cards from the files of fixtures/: cycles, packs, cards as printed, printings, external cards and
 * the starter decks (`starter_decks/<deck id>.json`: quantity by card code, without owner). What exists is updated,
 * what is missing is created, nothing is deleted (games and decks use the cards): it can run again on a database in use.
 */
readonly class CardDatabaseImporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private string $projectDir,
    ) {
    }

    /**
     * @return array<string, int> the number of records created, by kind
     */
    public function import(): array
    {
        $created = [
            'cycles' => $this->importCycles(),
            'packs' => $this->importPacks(),
            'cards' => $this->importCards(),
            'printings' => $this->importPrintings(),
            'external cards' => $this->importExternalCards(),
            'starter decks' => $this->importStarterDecks(),
        ];
        $this->entityManager->flush();

        return $created;
    }

    private function importCycles(): int
    {
        $created = 0;
        $cycles = $this->cycles();
        foreach ($this->files('cycles') as $contents) {
            $id = $this->readId($contents);
            $cycle = $cycles[$id] ?? null;
            if (! $cycle instanceof Cycle) {
                ++$created;
            }
            $cycle = $this->serializer->deserialize($contents, Cycle::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $cycle]);
            $this->entityManager->persist($cycle);
        }

        return $created;
    }

    /**
     * A pack names its cycle (`cycle_id`).
     */
    private function importPacks(): int
    {
        $created = 0;
        $cycles = $this->cycles();
        $packs = $this->packs();
        foreach ($this->files('packs') as $contents) {
            $id = $this->readId($contents);
            $pack = $packs[$id] ?? null;
            if (! $pack instanceof Pack) {
                ++$created;
            }
            $pack = $this->serializer->deserialize($contents, Pack::class, 'json', [
                AbstractNormalizer::OBJECT_TO_POPULATE => $pack,
                'datetime_format' => 'Y-m-d',
            ]);
            $cycleId = $this->readCycleId($contents);
            $pack->setCycle($cycles[$cycleId] ?? throw new \RuntimeException("Pack {$id} in the unknown cycle {$cycleId}."));
            $this->entityManager->persist($pack);
        }

        return $created;
    }

    private function importCards(): int
    {
        $created = 0;
        $cards = $this->cards();
        foreach ($this->files('cards') as $contents) {
            $id = $this->readId($contents);
            $card = $cards[$id] ?? null;
            if (! $card instanceof LogicalCard) {
                ++$created;
            }
            $card = $this->serializer->deserialize($contents, LogicalCard::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $card]);
            $this->entityManager->persist($card);
        }

        return $created;
    }

    /**
     * A printing is the card in a pack.
     */
    private function importPrintings(): int
    {
        $created = 0;
        $cards = $this->cards();
        $packs = $this->packs();
        $printings = [];
        foreach ($this->entityManager->getRepository(Printing::class)->findAll() as $printing) {
            $printings[$printing->getLogicalCard()->getId() . '|' . $printing->getPack()->getId()] = $printing;
        }
        foreach ($this->files('printings') as $contents) {
            $data = $this->serializer->deserialize($contents, PrintingData::class, 'json');
            $printing = $printings[$data->cardId . '|' . $data->packId] ?? null;
            if (! $printing instanceof Printing) {
                $printing = new Printing();
                $printing->setLogicalCard($cards[$data->cardId] ?? throw new \RuntimeException("Printing of the unknown card {$data->cardId}."));
                $printing->setPack($packs[$data->packId] ?? throw new \RuntimeException("Printing in the unknown pack {$data->packId}."));
                $this->entityManager->persist($printing);
                ++$created;
            }
            $printing->setIllustrator($data->illustrator);
            $printing->setFlavorText($data->flavorText);
            $printing->setPosition($data->position);
            $printing->setImageUrl($data->imageUrl);
            $printing->setQuantity($data->quantity);
        }

        return $created;
    }

    /**
     * One file per source (`<source>.json`): card code by external id.
     */
    private function importExternalCards(): int
    {
        $created = 0;
        $cards = $this->cards();
        $externalCards = [];
        foreach ($this->entityManager->getRepository(ExternalCard::class)->findAll() as $externalCard) {
            $externalCards[$externalCard->getSource()->value . '|' . $externalCard->getExternalId()] = $externalCard;
        }
        foreach ($this->files('external_cards') as $name => $contents) {
            $source = CardSource::from($name);
            /** @var array<string, string> $codes */
            $codes = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            foreach ($codes as $externalId => $code) {
                $card = $cards[$code] ?? throw new \RuntimeException("External card of the unknown card {$code}.");
                $externalCard = $externalCards[$source->value . '|' . $externalId] ?? null;
                if ($externalCard instanceof ExternalCard) {
                    $externalCard->setCard($card);
                } else {
                    $this->entityManager->persist(new ExternalCard($source, (string) $externalId, $card));
                    ++$created;
                }
            }
        }

        return $created;
    }

    private function importStarterDecks(): int
    {
        $created = 0;
        foreach ($this->files('starter_decks') as $id => $contents) {
            $deck = $this->entityManager->find(Deck::class, $id);
            if (! $deck instanceof Deck) {
                $deck = new Deck($id);
                $this->entityManager->persist($deck);
                ++$created;
            } elseif ($deck->getOwner() instanceof User) {
                throw new \RuntimeException("The deck {$id} belongs to a user: it is not a starter deck.");
            }
            /** @var array<string, int> $cards */
            $cards = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            $deck->setName($id);
            $deck->setCards($cards);
        }

        return $created;
    }

    /**
     * @return iterable<string, string> the contents of the JSON files of a directory of fixtures/ (subdirectories
     *                                  included), by name without extension, sorted by path
     */
    private function files(string $directory): iterable
    {
        $finder = new Finder()->files()->in("{$this->projectDir}/fixtures/{$directory}")->name('*.json')->sortByName();
        foreach ($finder as $file) {
            yield $file->getFilenameWithoutExtension() => $file->getContents();
        }
    }

    private function readId(string $contents): string
    {
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! \is_array($data) || ! \is_string($data['id'] ?? null)) {
            throw new \RuntimeException('A record of the card database has no id.');
        }

        return $data['id'];
    }

    private function readCycleId(string $contents): string
    {
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! \is_array($data) || ! \is_string($data['cycle_id'] ?? null)) {
            throw new \RuntimeException('A pack of the card database has no cycle.');
        }

        return $data['cycle_id'];
    }

    /**
     * @return array<string, Cycle> by id, those persisted since the last flush included
     */
    private function cycles(): array
    {
        return $this->indexById(Cycle::class);
    }

    /**
     * @return array<string, Pack> by id, those persisted since the last flush included
     */
    private function packs(): array
    {
        return $this->indexById(Pack::class);
    }

    /**
     * @return array<string, LogicalCard> by code, those persisted since the last flush included
     */
    private function cards(): array
    {
        return $this->indexById(LogicalCard::class);
    }

    /**
     * @template T of Cycle|Pack|LogicalCard
     *
     * @param class-string<T> $class
     *
     * @return array<string, T>
     */
    private function indexById(string $class): array
    {
        $this->entityManager->flush();
        $indexed = [];
        // not the repository: the one of LogicalCard reads the physical cards
        /** @var list<T> $entities */
        $entities = $this->entityManager->createQueryBuilder()->select('e')->from($class, 'e')->getQuery()->getResult();
        foreach ($entities as $entity) {
            $indexed[$entity->getId()] = $entity;
        }

        return $indexed;
    }
}
