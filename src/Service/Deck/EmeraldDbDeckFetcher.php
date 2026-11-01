<?php

declare(strict_types=1);

namespace App\Service\Deck;

use App\Exception\Api\DeckSourceUnavailableException;
use App\Exception\Api\InvalidDeckUrlException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Downloads a deck of EmeraldDB (https://www.emeralddb.org) from the URL of its page, through the HTTP client
 * `emeralddb.client` (config/packages/framework.yaml). The page of a published version is `/decks/<decklist id>`,
 * the editor of a deck `/builder/<deck id>/edit`; the API answers both ids at `/api/decklists/<id>` (a deck id gives
 * its latest version). Its cards are in the format `emeralddb` (EmeraldDbDeckFormat).
 */
readonly class EmeraldDbDeckFetcher
{
    private const array HOSTS = ['www.emeralddb.org', 'emeralddb.org'];

    private const int MAX_NAME_LENGTH = 255;

    private const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public function __construct(
        private HttpClientInterface $emeralddbClient,
    ) {
    }

    /**
     * @return array{name: ?string, cards: array<string, int>} quantity by EmeraldDB card id
     *
     * @throws InvalidDeckUrlException        not the URL of a deck of EmeraldDB, or a deck it does not show
     * @throws DeckSourceUnavailableException EmeraldDB cannot be reached or answers what is not a deck
     */
    public function fetch(string $url): array
    {
        $id = $this->parseId($url);

        try {
            $response = $this->emeralddbClient->request('GET', "/api/decklists/{$id}");
            if ($response->getStatusCode() === 404) {
                throw new InvalidDeckUrlException('EmeraldDB has no deck at this URL, or does not show it.');
            }
            $decklist = $response->toArray();
        } catch (ExceptionInterface $exception) {
            throw new DeckSourceUnavailableException('EmeraldDB cannot be reached, or does not answer a deck.', 0, $exception);
        }

        return $this->readDecklist($decklist);
    }

    private function parseId(string $url): string
    {
        $parts = parse_url(trim($url));
        $host = strtolower($parts['host'] ?? '');
        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || ! in_array($host, self::HOSTS, true)
            || preg_match('#^/(?:decks/(' . self::UUID . ')|builder/(' . self::UUID . ')/edit)/?$#i', $parts['path'] ?? '', $matches) !== 1) {
            throw new InvalidDeckUrlException('Not the URL of a deck of EmeraldDB, expected https://www.emeralddb.org/decks/<id>.');
        }

        return strtolower($matches[1] !== '' ? $matches[1] : $matches[2]);
    }

    /**
     * @param array<mixed> $decklist
     *
     * @return array{name: ?string, cards: array<string, int>}
     */
    private function readDecklist(array $decklist): array
    {
        $cards = $decklist['cards'] ?? null;
        if (! is_array($cards)) {
            throw new DeckSourceUnavailableException('EmeraldDB answered a deck without cards.');
        }

        $quantities = [];
        foreach ($cards as $id => $quantity) {
            if (! is_int($quantity)) {
                throw new DeckSourceUnavailableException('EmeraldDB answered a deck with invalid quantities.');
            }
            $quantities[(string) $id] = $quantity;
        }

        $name = $decklist['name'] ?? null;
        $name = is_string($name) ? mb_substr(trim($name), 0, self::MAX_NAME_LENGTH) : '';

        return [
            'name' => $name !== '' ? $name : null,
            'cards' => $quantities,
        ];
    }
}
