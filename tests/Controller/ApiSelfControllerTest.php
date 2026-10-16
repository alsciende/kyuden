<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\Game;
use App\Tests\AbstractKernelTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ApiSelfControllerTest extends AbstractKernelTestCase
{
    private function get(string $path, ?string $user): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request('GET', $path, server: array_filter([
            'HTTP_AUTHORIZATION' => $user === null ? null : 'Bearer ' . UserFixtures::TOKENS[$user],
        ]));

        return $client->getResponse();
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);

        return $content;
    }

    public function testAUserReadsTheirIdentifier(): void
    {
        self::assertSame(['user' => 'john'], $this->decode($this->get('/api/self', 'john')));
    }

    public function testAUserFindsTheGamesTheyPlay(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();

        $games = $this->decode($this->get('/api/self/games', 'jane'));

        self::assertSame([[
            'id' => $scenario->game()->getId(),
            'player_id' => $scenario->player('jane')->getId(),
            'version' => $scenario->game()->getVersion(),
            'ended' => false,
            'players' => ['john', 'jane'],
        ]], $games);
    }

    public function testTheEndedGamesAreListed(): void
    {
        $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();

        $games = $this->decode($this->get('/api/self/games', 'john'));

        self::assertCount(1, $games);
        self::assertIsArray($games[0]);
        self::assertTrue($games[0]['ended']);
    }

    public function testTheGamesOfOtherUsersAreNotListed(): void
    {
        $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();

        self::assertSame([], $this->decode($this->get('/api/self/games', 'akodo-bot')));
    }

    public function testTheGamesRequireAToken(): void
    {
        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->get('/api/self/games', null)->getStatusCode());
    }
}
