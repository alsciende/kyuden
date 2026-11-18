<?php

declare(strict_types=1);

namespace App\Tests\Service\Deck;

use App\Entity\Deck;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Exception\Api\DeckInTournamentException;
use App\Service\Deck\DeckDeleter;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class DeckDeleterTest extends AbstractKernelTestCase
{
    /**
     * A deck of john registered in a tournament of jane.
     */
    private function registeredDeck(bool $cancelled): Deck
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $john = $entityManager->find(User::class, 'john');
        $jane = $entityManager->find(User::class, 'jane');
        self::assertInstanceOf(User::class, $john);
        self::assertInstanceOf(User::class, $jane);
        $now = new \DateTimeImmutable();
        $deck = new Deck('deck-of-john')->setName('Hida')->setOwner($john)->setCards(['hida-kisada' => 1]);
        $tournament = new Tournament('tournament', $jane, $now->modify('+2 days'), random_bytes(32), $now);
        if ($cancelled) {
            $tournament->cancel();
        }
        $entityManager->persist($deck);
        $entityManager->persist($tournament);
        $entityManager->persist(new TournamentEntry($tournament, $john, $deck, $now));
        $entityManager->flush();

        return $deck;
    }

    public function testADeckRegisteredInATournamentStillToBePlayedIsNotDeleted(): void
    {
        $deck = $this->registeredDeck(cancelled: false);

        $this->expectException(DeckInTournamentException::class);

        self::service(DeckDeleter::class)->delete($deck);
    }

    public function testADeckRegisteredInATournamentNoLongerToBePlayedIsMarkedDeleted(): void
    {
        $deck = $this->registeredDeck(cancelled: true);

        self::service(DeckDeleter::class)->delete($deck);
        self::service(EntityManagerInterface::class)->flush();

        self::assertTrue($deck->isDeleted());
        self::assertSame($deck, $this->deckRepository->find('deck-of-john'));
    }
}
