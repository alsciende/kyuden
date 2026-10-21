<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Tests\Builder\PlayerBuilder;

class GetGameToolTest extends AbstractMcpTestCase
{
    public function testTheBotReadsTheGameAsItSeesIt(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('akodo-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->hand(['01-above-question']))
            ->withPlayer('hida-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->hand(['01-above-question']))
            ->activePlayer('akodo-bot')
            ->persist();

        $result = $this->callTool('get_game', ['game_id' => $scenario->game()->getId()]);

        self::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertIsArray($result['structuredContent']);
        /** @var array{place: string, viewer_id: string, available_actions: list<string>, players: list<array{id: string, stacks: list<array{type: string, cards: list<array{code: ?string}>|null}>}>} $view */
        $view = $result['structuredContent'];
        self::assertSame('draw_phase_action_window', $view['place']);
        self::assertSame($scenario->player('akodo-bot')->getId(), $view['viewer_id']);
        self::assertContains('pass_opportunity', $view['available_actions']);
        $hands = [];
        foreach ($view['players'] as $player) {
            foreach ($player['stacks'] as $stack) {
                if ($stack['type'] === 'hand') {
                    $hands[$player['id']] = $stack['cards'];
                }
            }
        }
        // its own hand, not the opponent's
        self::assertSame([['code' => '01-above-question']], array_map(
            fn (array $card): array => ['code' => $card['code']],
            $hands[$scenario->player('akodo-bot')->getId()] ?? [],
        ));
        self::assertNull($hands[$scenario->player('hida-bot')->getId()]);
    }

    public function testTheBotMustBeAPlayerOfTheGame(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('hida-bot')
            ->withPlayer('john')
            ->persist();

        $result = $this->callTool('get_game', ['game_id' => $scenario->game()->getId()]);

        self::assertTrue($result['isError']);
    }

    public function testTheGameMustExist(): void
    {
        $result = $this->callTool('get_game', ['game_id' => 'unknown']);

        self::assertTrue($result['isError']);
    }
}
