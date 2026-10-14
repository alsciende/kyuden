<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class ApiGamesControllerTest extends AbstractKernelTestCase
{
    private function scenarioWithHands(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->hand(['01-fine-katana']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->hand(['01-banzai']))
            ->persist();
    }

    private function get(string $gameId, ?string $user = 'john'): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request('GET', "/api/games/{$gameId}", server: array_filter([
            'HTTP_AUTHORIZATION' => $user === null ? null : "Bearer {$user}",
        ]));

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

    public function testThePlayerReadsTheGameAsTheySeeIt(): void
    {
        $scenario = $this->scenarioWithHands();

        $response = $this->get($scenario->game()->getId());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $game = $this->decode($response);
        self::assertSame($scenario->game()->getId(), $game['id']);
        self::assertSame('draw_phase_action_window', $game['place']);
        self::assertSame($scenario->player('john')->getId(), $game['viewer_id']);
        self::assertIsArray($game['players']);
        [$john, $jane] = $game['players'];
        self::assertIsArray($john);
        self::assertIsArray($jane);
        self::assertSame(10, $john['honor']);
        self::assertSame([['type' => 'hand', 'count' => 1, 'codes' => ['01-fine-katana']]], $this->hands($john));
        self::assertSame([['type' => 'hand', 'count' => 1, 'codes' => null]], $this->hands($jane));
    }

    /**
     * @param array<mixed> $player
     *
     * @return list<array{type: mixed, count: mixed, codes: list<mixed>|null}>
     */
    private function hands(array $player): array
    {
        self::assertIsArray($player['stacks']);
        $hands = [];
        foreach ($player['stacks'] as $stack) {
            self::assertIsArray($stack);
            if ($stack['type'] === 'hand') {
                $hands[] = [
                    'type' => $stack['type'],
                    'count' => $stack['count'],
                    'codes' => is_array($stack['cards']) ? array_column($stack['cards'], 'code') : null,
                ];
            }
        }

        return $hands;
    }

    public function testAUserWhoIsNotAPlayerOfTheGameCannotReadIt(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jack')
            ->persist();

        $response = $this->get($scenario->game()->getId(), 'jane');

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame('not_a_player', $this->decode($response)['type']);
    }

    public function testAnAnonymousUserCannotReadAGame(): void
    {
        $scenario = $this->scenarioWithHands();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->get($scenario->game()->getId(), null)->getStatusCode());
    }

    public function testTheGameMustExist(): void
    {
        $response = $this->get('unknown');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('game_not_found', $this->decode($response)['type']);
    }
}
