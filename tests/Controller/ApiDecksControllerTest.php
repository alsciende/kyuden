<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Deck;
use App\Entity\User;
use App\Service\MatchMaking\MatchMakingQueue;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Service\Deck\DeckConstructionValidatorTest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
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

    private const string EMERALDDB_URL = 'https://www.emeralddb.org/decks/d95c6eda-57ce-49a8-b03e-b40ab603f293';

    /**
     * EmeraldDB answers this response to every request.
     */
    private function emeraldDbAnswers(MockResponse $response): void
    {
        $client = self::getContainer()->get('app.test.emeralddb_client');
        self::assertInstanceOf(MockHttpClient::class, $client);
        $client->setResponseFactory($response);
    }

    public function testAUserImportsADeckFromItsURLOnEmeraldDb(): void
    {
        $this->emeraldDbAnswers(new JsonMockResponse(['name' => 'Moist Crane Duel', 'cards' => $this->emeraldDbCraneDeck()]));

        $response = $this->request('POST', '/api/decks', ['url' => self::EMERALDDB_URL]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $view = $this->decode($response);
        self::assertIsString($view['id']);
        self::assertSame("/api/decks/{$view['id']}", $response->headers->get('Location'));
        self::assertSame('Moist Crane Duel', $view['name']);
        self::assertSame('akodo-bot', $view['owner_id']);
        self::assertSame(DeckConstructionValidatorTest::craneDeck(), $view['cards']);
    }

    public function testTheNameGivenReplacesTheNameOnEmeraldDb(): void
    {
        $this->emeraldDbAnswers(new JsonMockResponse(['name' => 'Moist Crane Duel', 'cards' => $this->emeraldDbCraneDeck()]));

        $response = $this->request('POST', '/api/decks', ['name' => 'Kakita duels', 'url' => self::EMERALDDB_URL]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('Kakita duels', $this->decode($response)['name']);
    }

    public function testAURLComesWithoutCards(): void
    {
        $response = $this->request('POST', '/api/decks', ['url' => self::EMERALDDB_URL, 'format' => 'emeralddb', 'cards' => $this->emeraldDbCraneDeck()]);

        self::assertEqualsCanonicalizing(['format', 'cards'], $this->violations($response, Response::HTTP_BAD_REQUEST, 'invalid_payload'));
    }

    public function testTheURLIsAString(): void
    {
        $response = $this->request('POST', '/api/decks', ['url' => 42]);

        self::assertSame(['url'], $this->violations($response, Response::HTTP_BAD_REQUEST, 'invalid_payload'));
    }

    public function testTheURLNamesADeckOfEmeraldDb(): void
    {
        $response = $this->request('POST', '/api/decks', ['url' => 'https://www.fiveringsdb.com/decks/d95c6eda-57ce-49a8-b03e-b40ab603f293']);

        self::assertSame(['url'], $this->violations($response, Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_deck'));
    }

    public function testADeckEmeraldDbDoesNotHaveIsInvalid(): void
    {
        $this->emeraldDbAnswers(new MockResponse('Not Found', ['http_code' => 404]));

        $response = $this->request('POST', '/api/decks', ['url' => self::EMERALDDB_URL]);

        self::assertSame(['url'], $this->violations($response, Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_deck'));
    }

    public function testADeckOfEmeraldDbFollowsTheDeckbuildingRules(): void
    {
        $this->emeraldDbAnswers(new JsonMockResponse(['name' => 'Emerald', 'cards' => ['a-new-card' => 1, ...$this->emeraldDbCraneDeck()]]));

        $response = $this->request('POST', '/api/decks', ['url' => self::EMERALDDB_URL]);

        self::assertSame(['cards[a-new-card]'], $this->violations($response, Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_deck'));
    }

    public function testEmeraldDbUnavailableIsABadGateway(): void
    {
        $this->emeraldDbAnswers(new MockResponse('', ['http_code' => 503]));

        $response = $this->request('POST', '/api/decks', ['url' => self::EMERALDDB_URL]);

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertSame('deck_source_unavailable', $this->decode($response)['type']);
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

    public function testADeckTheUserHasIsNotImportedTwice(): void
    {
        $first = $this->decode($this->request('POST', '/api/decks', ['name' => 'Moist Crane Duel', 'format' => 'emeralddb', 'cards' => $this->emeraldDbCraneDeck()]));

        $response = $this->request('POST', '/api/decks', ['name' => 'Kakita duels', 'format' => 'codes', 'cards' => array_reverse(DeckConstructionValidatorTest::craneDeck(), true)]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $view = $this->decode($response);
        self::assertIsString($first['id']);
        self::assertSame($first['id'], $view['id']);
        self::assertSame('Moist Crane Duel', $view['name']);
        self::assertSame("/api/decks/{$first['id']}", $response->headers->get('Location'));
    }

    public function testADeckOfAnotherUserIsNoDuplicate(): void
    {
        $this->request('POST', '/api/decks', ['format' => 'codes', 'cards' => DeckConstructionValidatorTest::craneDeck()], 'hida-bot');

        $response = $this->request('POST', '/api/decks', ['format' => 'codes', 'cards' => DeckConstructionValidatorTest::craneDeck()]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testADeckIdenticalToADeckWithoutOwnerIsImported(): void
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $shared = new Deck('shared-crane-deck');
        $shared->setCards(DeckConstructionValidatorTest::craneDeck());
        $entityManager->persist($shared);
        $entityManager->flush();

        $response = $this->request('POST', '/api/decks', ['format' => 'codes', 'cards' => DeckConstructionValidatorTest::craneDeck()]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        self::assertNotSame('shared-crane-deck', $this->decode($response)['id']);
    }

    public function testAUserDeletesADeckNeverPlayed(): void
    {
        $this->createDeckOf('akodo-bot');

        $response = $this->request('DELETE', '/api/decks/deck-of-akodo-bot');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        self::service(EntityManagerInterface::class)->clear();
        self::assertNull($this->deckRepository->find('deck-of-akodo-bot'));
    }

    public function testADeletedDeckPlayedInAGameStaysForItsPlayers(): void
    {
        $this->createDeckOf('akodo-bot');
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('akodo-bot', fn ($player) => $player->deck('deck-of-akodo-bot'))
            ->withPlayer('hida-bot')
            ->persist();

        $response = $this->request('DELETE', '/api/decks/deck-of-akodo-bot');

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $deck = $this->deckRepository->find('deck-of-akodo-bot');
        self::assertInstanceOf(Deck::class, $deck);
        self::assertTrue($deck->isDeleted());
        self::assertSame($deck, $scenario->player('akodo-bot')->getDeck());
        self::assertNotContains('deck-of-akodo-bot', array_column($this->decode($this->request('GET', '/api/decks')), 'id'));
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('GET', '/api/decks/deck-of-akodo-bot')->getStatusCode());
        self::assertSame(Response::HTTP_NOT_FOUND, $this->request('DELETE', '/api/decks/deck-of-akodo-bot')->getStatusCode());
    }

    public function testImportingADeletedDeckRestoresIt(): void
    {
        $first = $this->decode($this->request('POST', '/api/decks', ['name' => 'Moist Crane Duel', 'format' => 'codes', 'cards' => DeckConstructionValidatorTest::craneDeck()]));
        self::assertIsString($first['id']);
        $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('akodo-bot', fn ($player) => $player->deck($first['id']))
            ->withPlayer('hida-bot')
            ->persist();
        $this->request('DELETE', "/api/decks/{$first['id']}");

        $response = $this->request('POST', '/api/decks', ['name' => 'Kakita duels', 'format' => 'codes', 'cards' => DeckConstructionValidatorTest::craneDeck()]);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        $view = $this->decode($response);
        self::assertSame($first['id'], $view['id']);
        self::assertSame('Kakita duels', $view['name']);
        self::assertContains($first['id'], array_column($this->decode($this->request('GET', '/api/decks')), 'id'));
    }

    public function testADeckTheUserWaitsWithCannotBeDeleted(): void
    {
        $deck = $this->createDeckOf('akodo-bot');
        $owner = $deck->getOwner();
        self::assertInstanceOf(User::class, $owner);
        self::service(MatchMakingQueue::class)->join($owner, 'deck-of-akodo-bot');

        $response = $this->request('DELETE', '/api/decks/deck-of-akodo-bot');

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('deck_in_queue', $this->decode($response)['type']);
        self::assertInstanceOf(Deck::class, $this->deckRepository->find('deck-of-akodo-bot'));
    }

    public function testTheStarterDecksAndTheDecksOfOtherUsersCannotBeDeleted(): void
    {
        $this->createDeckOf('hida-bot');

        foreach (['deck-of-hida-bot', AppFixtures::LION_STARTER_DECK, 'unknown'] as $id) {
            $response = $this->request('DELETE', "/api/decks/{$id}");

            self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), $id);
            self::assertSame('deck_not_found', $this->decode($response)['type']);
        }
        self::assertInstanceOf(Deck::class, $this->deckRepository->find(AppFixtures::LION_STARTER_DECK));
        self::assertInstanceOf(Deck::class, $this->deckRepository->find('deck-of-hida-bot'));
    }

    public function testTheDecksRequireAToken(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->request('GET', '/api/decks', user: null)->getStatusCode());
    }
}
