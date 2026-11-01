<?php

declare(strict_types=1);

namespace App\Tests\Service\Deck;

use App\Exception\Api\DeckSourceUnavailableException;
use App\Exception\Api\InvalidDeckUrlException;
use App\Service\Deck\EmeraldDbDeckFetcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class EmeraldDbDeckFetcherTest extends TestCase
{
    private const string ID = 'd95c6eda-57ce-49a8-b03e-b40ab603f293';

    /**
     * @param list<string> $requested
     */
    private function fetcher(MockResponse $response, array &$requested = []): EmeraldDbDeckFetcher
    {
        return new EmeraldDbDeckFetcher(new MockHttpClient(function (string $method, string $url) use ($response, &$requested): MockResponse {
            $requested[] = "{$method} {$url}";

            return $response;
        }, 'https://www.emeralddb.org'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function deckUrls(): iterable
    {
        yield 'published version' => ['https://www.emeralddb.org/decks/' . self::ID, self::ID];
        yield 'trailing slash, no www' => ['http://emeralddb.org/decks/' . self::ID . '/', self::ID];
        yield 'upper case, query and spaces' => ['  HTTPS://WWW.EMERALDDB.ORG/decks/' . strtoupper(self::ID) . '?tab=1 ', self::ID];
        yield 'editor of the deck' => ['https://www.emeralddb.org/builder/' . self::ID . '/edit', self::ID];
    }

    #[DataProvider('deckUrls')]
    public function testTheDeckOfTheURLIsDownloaded(string $url, string $id): void
    {
        $requested = [];
        $fetched = $this->fetcher(new JsonMockResponse(['name' => ' Moist Crane Duel ', 'cards' => ['kyuden-kakita' => 1, 'banzai' => 3]]), $requested)->fetch($url);

        self::assertSame(['name' => 'Moist Crane Duel', 'cards' => ['kyuden-kakita' => 1, 'banzai' => 3]], $fetched);
        self::assertSame(["GET https://www.emeralddb.org/api/decklists/{$id}"], $requested);
    }

    public function testADeckWithoutNameHasNone(): void
    {
        $fetched = $this->fetcher(new JsonMockResponse(['name' => '', 'cards' => []]))->fetch('https://www.emeralddb.org/decks/' . self::ID);

        self::assertNull($fetched['name']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherUrls(): iterable
    {
        yield 'another site' => ['https://www.fiveringsdb.com/decks/' . self::ID];
        yield 'a site ending like it' => ['https://notemeralddb.org/decks/' . self::ID];
        yield 'no scheme' => ['www.emeralddb.org/decks/' . self::ID];
        yield 'another scheme' => ['ftp://www.emeralddb.org/decks/' . self::ID];
        yield 'not a deck' => ['https://www.emeralddb.org/card/' . self::ID];
        yield 'not an id' => ['https://www.emeralddb.org/decks/../api/cards'];
        yield 'the list of decks' => ['https://www.emeralddb.org/decks'];
        yield 'not a URL' => ['moist crane duel'];
    }

    #[DataProvider('otherUrls')]
    public function testOnlyTheURLOfADeckOfEmeraldDbIsAccepted(string $url): void
    {
        $requested = [];
        try {
            $this->fetcher(new JsonMockResponse([]), $requested)->fetch($url);
            self::fail('The URL is accepted.');
        } catch (InvalidDeckUrlException) {
            self::assertSame([], $requested);
        }
    }

    public function testADeckEmeraldDbDoesNotHaveIsNotFound(): void
    {
        $this->expectException(InvalidDeckUrlException::class);
        $this->fetcher(new MockResponse('Not Found', ['http_code' => 404]))->fetch('https://www.emeralddb.org/decks/' . self::ID);
    }

    /**
     * @return iterable<string, array{MockResponse}>
     */
    public static function unavailableResponses(): iterable
    {
        yield 'server error' => [new MockResponse('', ['http_code' => 503])];
        yield 'redirection' => [new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://example.com']])];
        yield 'network error' => [new MockResponse('', ['error' => 'Connection refused'])];
        yield 'not JSON' => [new MockResponse('<!DOCTYPE html>', ['response_headers' => ['Content-Type' => 'text/html']])];
        yield 'no cards' => [new JsonMockResponse(['name' => 'A deck'])];
        yield 'a quantity not a number' => [new JsonMockResponse(['cards' => ['banzai' => '3']])];
    }

    #[DataProvider('unavailableResponses')]
    public function testWhatIsNotADeckIsAnError(MockResponse $response): void
    {
        $this->expectException(DeckSourceUnavailableException::class);
        $this->fetcher($response)->fetch('https://www.emeralddb.org/decks/' . self::ID);
    }
}
