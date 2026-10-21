<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Entity\Game;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * list_my_games and wait_for_game.
 */
class GamesToolsTest extends AbstractMcpTestCase
{
    use ClockSensitiveTrait;

    /**
     * @param array<mixed> $result
     *
     * @return list<array<mixed>>
     */
    private function games(array $result): array
    {
        self::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertIsArray($result['structuredContent']);
        self::assertIsList($result['structuredContent']['games']);

        /** @var list<array<mixed>> */
        return $result['structuredContent']['games'];
    }

    public function testTheBotListsItsGames(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('hida-bot')
            ->withPlayer('akodo-bot')
            ->persist();
        $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('hida-bot')
            ->withPlayer('john')
            ->persist();

        $games = $this->games($this->callTool('list_my_games', []));

        self::assertSame([[
            'id' => $scenario->game()->getId(),
            'player_id' => $scenario->player('akodo-bot')->getId(),
            'version' => 0,
            'ended' => false,
            'players' => ['hida-bot', 'akodo-bot'],
        ]], $games);
    }

    public function testTheBotGetsItsGameInProgressAtOnce(): void
    {
        $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('akodo-bot')
            ->withPlayer('hida-bot')
            ->persist();
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('akodo-bot')
            ->withPlayer('hida-bot')
            ->persist();
        $clock = self::mockTime();
        $start = (float) $clock->now()->format('U.u');

        $games = $this->games($this->callTool('wait_for_game', ['timeout' => 5]));

        // the ended game is not in progress
        self::assertSame([$scenario->game()->getId()], array_column($games, 'id'));
        self::assertSame(0.0, (float) $clock->now()->format('U.u') - $start);
    }

    public function testTheBotGetsNoGameWhenNoneStartsInTime(): void
    {
        $clock = self::mockTime();
        $start = (float) $clock->now()->format('U.u');

        $games = $this->games($this->callTool('wait_for_game', ['timeout' => 5]));

        self::assertSame([], $games);
        self::assertSame(5.0, round((float) $clock->now()->format('U.u') - $start, 3));
    }

    public function testTheTimeoutIsBounded(): void
    {
        $response = $this->request('tools/call', ['name' => 'wait_for_game', 'arguments' => ['timeout' => 31]]);

        self::assertArrayHasKey('error', $response);
    }
}
