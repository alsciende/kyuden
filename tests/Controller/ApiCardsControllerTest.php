<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AbstractKernelTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ApiCardsControllerTest extends AbstractKernelTestCase
{
    private function get(string $path): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);

        $client->request('GET', $path);

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

    public function testAnyoneReadsACardByItsCode(): void
    {
        $response = $this->get('/api/cards/01-adept-of-the-waves');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertTrue($response->headers->getCacheControlDirective('public'));
        self::assertSame([
            'code' => '01-adept-of-the-waves',
            'name' => 'Adept of the Waves',
            'side' => 'dynasty',
            'type' => 'character',
            'clan' => 'phoenix',
            'uniqueness' => false,
            'cost' => 2,
            'traits' => ['shugenja', 'water'],
            'text' => '<b>Action:</b> Choose a character – until the end of the phase, that character gains covert during [element-water] conflicts.',
            'allowed_clans' => ['phoenix'],
            'deck_limit' => 3,
            'role_restriction' => null,
            'influence_cost' => null,
            'influence_pool' => null,
            'elements' => null,
            'fate' => null,
            'honor' => null,
            'glory' => 2,
            'military' => '2',
            'military_bonus' => null,
            'political' => '2',
            'political_bonus' => null,
            'strength' => null,
            'strength_bonus' => null,
            'ability_timings' => ['action'],
            'ability_granted_to_attached' => false,
        ], $this->decode($response));
    }

    public function testACardPrintedWithEveryElementHasTheFiveElements(): void
    {
        $response = $this->get('/api/cards/19-toshi-ranbo');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['air', 'earth', 'fire', 'void', 'water'], $this->decode($response)['elements']);
    }

    public function testAnUnknownCodeIsNotFound(): void
    {
        $response = $this->get('/api/cards/01-unknown');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('card_not_found', $this->decode($response)['type']);
    }

    public function testAnyoneReadsEveryCardSortedByCode(): void
    {
        $response = $this->get('/api/cards');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        self::assertTrue($response->headers->getCacheControlDirective('public'));
        $codes = array_column($this->decode($response), 'code');
        self::assertContains('01-fine-katana', $codes);
        $sorted = $codes;
        sort($sorted);
        self::assertSame($sorted, $codes);
    }
}
