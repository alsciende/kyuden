<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Deck;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Service\Deck\DeckConstructionValidatorTest;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class DeckImportCommandTest extends AbstractKernelTestCase
{
    private const string URL = 'https://www.emeralddb.org/decks/d95c6eda-57ce-49a8-b03e-b40ab603f293';

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:deck:import'));
    }

    /**
     * EmeraldDB answers this deck.
     *
     * @param array<string, int> $cards
     */
    private function emeraldDbAnswers(array $cards): void
    {
        $client = static::getContainer()->get('app.test.emeralddb_client');
        self::assertInstanceOf(MockHttpClient::class, $client);
        $client->setResponseFactory(new JsonMockResponse(['name' => 'Moist Crane Duel', 'cards' => $cards]));
    }

    /**
     * @return array<string, int>
     */
    private function emeraldDbCraneDeck(): array
    {
        /** @var array<string, int> $cards */
        $cards = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/emeralddb-crane-deck.json'), true, flags: \JSON_THROW_ON_ERROR);

        return $cards;
    }

    /**
     * @return list<Deck>
     */
    private function decksOf(string $userId): array
    {
        return $this->deckRepository->findBy(['owner' => $userId]);
    }

    public function testADeckOfEmeraldDbIsImportedForAUser(): void
    {
        $this->emeraldDbAnswers($this->emeraldDbCraneDeck());
        $before = count($this->decksOf('john'));

        $tester = $this->tester();
        $tester->execute(['url' => self::URL, '--user' => 'john']);

        $tester->assertCommandIsSuccessful();
        $decks = $this->decksOf('john');
        self::assertCount($before + 1, $decks);
        $deck = array_find($decks, fn (Deck $deck): bool => $deck->getName() === 'Moist Crane Duel');
        self::assertInstanceOf(Deck::class, $deck);
        self::assertSame(DeckConstructionValidatorTest::craneDeck(), $deck->getCards());
        self::assertStringContainsString("Deck {$deck->getId()} imported for john", $tester->getDisplay());
    }

    public function testTheOwnerIsFoundByUsernameAndTheDeckRenamed(): void
    {
        $this->emeraldDbAnswers($this->emeraldDbCraneDeck());

        $this->tester()->execute(['url' => self::URL, '--user' => 'JOHN', '--name' => 'Kakita duels']);

        self::assertNotNull(array_find($this->decksOf('john'), fn (Deck $deck): bool => $deck->getName() === 'Kakita duels'));
    }

    public function testADeckBreakingTheRulesIsNotImported(): void
    {
        $cards = $this->emeraldDbCraneDeck();
        unset($cards['kyuden-kakita']);
        $this->emeraldDbAnswers(['a-new-card' => 1, ...$cards]);
        $before = count($this->decksOf('john'));

        $tester = $this->tester();
        $tester->execute(['url' => self::URL, '--user' => 'john']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('cards[a-new-card]: Unknown card.', $tester->getDisplay());
        self::assertCount($before, $this->decksOf('john'));
    }

    public function testTheOwnerIsRequired(): void
    {
        $this->expectExceptionMessage('The owner of the deck is required (--user).');
        $this->tester()->execute(['url' => self::URL]);
    }

    public function testTheOwnerExists(): void
    {
        $this->expectExceptionMessage('User nobody not found.');
        $this->tester()->execute(['url' => self::URL, '--user' => 'nobody']);
    }
}
