<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Deck;
use App\Entity\User;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Service\Deck\DeckConstructionValidatorTest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ApiDecksControllerTest extends AbstractKernelTestCase
{
    /**
     * @param array<mixed>|string|null $body
     */
    private function request(string $method, string $path, array|string|null $body = null, ?string $user = 'akodo-bot'): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request(
            $method,
            $path,
            server: array_filter([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => $user === null ? null : 'Bearer ' . UserFixtures::TOKENS[$user],
            ]),
            content: $body === null ? null : (is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR)),
        );

        return $client->getResponse();
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        $content = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);

        return $content;
    }

    /**
     * @return list<mixed> the property paths of the violations of the problem
     */
    private function violations(Response $response, int $status, string $type): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        $problem = $this->decode($response);
        self::assertSame($type, $problem['type']);
        self::assertIsArray($problem['violations']);

        return array_column($problem['violations'], 'property_path');
    }

    /**
     * @return array<string, int>
     */
    private function emeraldDbCraneDeck(): array
    {
        $cards = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/emeralddb-crane-deck.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($cards);
        /** @var array<string, int> $cards */

        return $cards;
    }

    private function createDeckOf(string $userId): Deck
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $owner = $entityManager->find(User::class, $userId);
        self::assertInstanceOf(User::class, $owner);
        $deck = new Deck("deck-of-{$userId}");
        $deck->setOwner($owner);
        $deck->setCards(['01-banzai' => 3]);
        $entityManager->persist($deck);
        $entityManager->flush();

        return $deck;
    }

    public function testAUserImportsADeckFromEmeraldDb(): void
    {
        $response = $this->request('POST', '/api/decks', ['name' => 'Moist Crane Duel', 'format' => 'emeralddb', 'cards' => $this->emeraldDbCraneDeck()]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $view = $this->decode($response);
        self::assertIsString($view['id']);
        self::assertSame("/api/decks/{$view['id']}", $response->headers->get('Location'));
        self::assertSame('Moist Crane Duel', $view['name']);
        self::assertSame('akodo-bot', $view['owner_id']);
        self::assertSame(DeckConstructionValidatorTest::craneDeck(), $view['cards']);

        $deck = $this->deckRepository->find($view['id']);
        self::assertInstanceOf(Deck::class, $deck);
        self::assertSame(91, count($deck->getCardCodes()));
    }

    public function testAUserImportsADeckByTheCodesOfTheCards(): void
    {
        $response = $this->request('POST', '/api/decks', ['format' => 'codes', 'cards' => DeckConstructionValidatorTest::craneDeck()]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull($this->decode($response)['name']);
    }

    public function testTheStructureOfTheDeckIsChecked(): void
    {
        $response = $this->request('POST', '/api/decks', ['format' => 3, 'cards' => ['01-banzai' => 0, '01-charge' => '2'], 'clan' => 'crab']);

        self::assertEqualsCanonicalizing(['clan', 'format', 'cards[01-banzai]', 'cards[01-charge]'], $this->violations($response, Response::HTTP_BAD_REQUEST, 'invalid_payload'));
    }

    public function testTheCardsAreAnObject(): void
    {
        $response = $this->request('POST', '/api/decks', ['format' => 'codes', 'cards' => ['01-banzai']]);

        self::assertSame(['cards'], $this->violations($response, Response::HTTP_BAD_REQUEST, 'invalid_payload'));
    }

    public function testTheFormatIsKnown(): void
    {
        $response = $this->request('POST', '/api/decks', ['format' => 'fiveringsdb', 'cards' => $this->emeraldDbCraneDeck()]);

        self::assertSame(['format'], $this->violations($response, Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_deck'));
    }

    public function testEveryCardIsKnownToTheFormat(): void
    {
        $response = $this->request('POST', '/api/decks', ['format' => 'emeralddb', 'cards' => ['a-new-card' => 1, ...$this->emeraldDbCraneDeck()]]);

        self::assertSame(['cards[a-new-card]'], $this->violations($response, Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_deck'));
    }

    public function testTheDeckFollowsTheDeckbuildingRules(): void
    {
        $cards = $this->emeraldDbCraneDeck();
        unset($cards['kyuden-kakita']);

        $response = $this->request('POST', '/api/decks', ['format' => 'emeralddb', 'cards' => $cards]);

        self::assertSame(['cards'], $this->violations($response, Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_deck'));
        $violations = $this->decode($response)['violations'];
        self::assertIsArray($violations);
        self::assertSame([['property_path' => 'cards', 'message' => 'The deck must have exactly 1 stronghold.']], $violations);
    }

    public function testAUserListsTheirDecksAndTheStarterDecks(): void
    {
        $this->createDeckOf('akodo-bot');
        $this->createDeckOf('hida-bot');

        $response = $this->request('GET', '/api/decks');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $ids = array_column($this->decode($response), 'id');
        self::assertContains('deck-of-akodo-bot', $ids);
        self::assertContains(AppFixtures::LION_STARTER_DECK, $ids);
        self::assertNotContains('deck-of-hida-bot', $ids);
    }

    public function testAUserReadsADeckTheyCanUse(): void
    {
        $this->createDeckOf('akodo-bot');

        $response = $this->request('GET', '/api/decks/deck-of-akodo-bot');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['id' => 'deck-of-akodo-bot', 'name' => null, 'owner_id' => 'akodo-bot', 'cards' => ['01-banzai' => 3]], $this->decode($response));
    }

    public function testTheDecksOfOtherUsersAreNotFound(): void
    {
        $this->createDeckOf('hida-bot');

        $response = $this->request('GET', '/api/decks/deck-of-hida-bot');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('deck_not_found', $this->decode($response)['type']);
    }

    public function testTheDecksRequireAToken(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('GET', '/api/decks', user: null)->getStatusCode());
    }
}
