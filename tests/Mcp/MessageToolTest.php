<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Service\Api\MessageSchemaFactory;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class MessageToolTest extends AbstractMcpTestCase
{
    private function scenario2Bots(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('akodo-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('hida-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->persist();
    }

    /**
     * @param array<mixed> $result
     *
     * @return array<mixed> the problem the tool error holds
     */
    private function problem(array $result): array
    {
        self::assertTrue($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertIsArray($result['content']);
        self::assertIsArray($result['content'][0]);
        self::assertIsString($result['content'][0]['text']);
        $problem = json_decode($result['content'][0]['text'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);

        return $problem;
    }

    public function testEveryPublicMessageIsATool(): void
    {
        $schemaFactory = self::getContainer()->get(MessageSchemaFactory::class);
        self::assertInstanceOf(MessageSchemaFactory::class, $schemaFactory);
        $schemas = $schemaFactory->createAll();

        $result = $this->request('tools/list')['result'];

        self::assertIsArray($result);
        self::assertIsArray($result['tools']);
        $tools = array_column($result['tools'], null, 'name');
        self::assertSame([], array_diff(array_keys($schemas), array_keys($tools)));
        self::assertIsArray($tools['lose_honor']);
        self::assertSame([
            'type' => 'object',
            'properties' => [
                'game_id' => ['description' => 'Id of the game.', 'type' => 'string'],
                'expected_version' => [
                    'description' => 'Version of the game you saw (wait_for_update): the action is rejected if the game has changed since.',
                    'type' => 'integer',
                    'minimum' => 0,
                ],
                'target_player_id' => ['description' => 'Player who loses honor.', 'type' => 'string'],
                'honor_loss' => ['description' => 'Honor lost.', 'type' => 'integer', 'minimum' => 0],
            ],
            'required' => ['game_id', 'target_player_id', 'honor_loss'],
            'additionalProperties' => false,
        ], $tools['lose_honor']['inputSchema']);
    }

    public function testTheBotSendsAMessageAsItsPlayer(): void
    {
        $scenario = $this->scenario2Bots();
        $hidaId = $scenario->player('hida-bot')->getId();

        $result = $this->callTool('lose_honor', [
            'game_id' => $scenario->game()->getId(),
            'target_player_id' => $hidaId,
            'honor_loss' => 2,
        ]);

        self::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertSame(['version' => 1], $result['structuredContent']);
        $player = $this->playerRepository->find($hidaId);
        self::assertNotNull($player);
        self::assertSame(8, $this->playerStateManager->getState($player)->getHonor());
    }

    public function testTheMessageIsRejectedWhenTheGameHasChangedSinceTheExpectedVersion(): void
    {
        $scenario = $this->scenario2Bots();
        $arguments = ['game_id' => $scenario->game()->getId(), 'target_player_id' => $scenario->player('hida-bot')->getId(), 'honor_loss' => 1];
        $this->callTool('lose_honor', $arguments);

        $problem = $this->problem($this->callTool('lose_honor', [...$arguments, 'expected_version' => 0]));

        self::assertSame('stale_version', $problem['type']);
        self::assertSame(412, $problem['status']);
    }

    public function testTheMessageIsRejectedByTheRules(): void
    {
        $scenario = $this->scenario2Bots();

        // Akodo bot, the first player, has the opportunity to act
        $problem = $this->problem($this->callTool('pass_opportunity', ['game_id' => $scenario->game()->getId()], 'hida-bot'));

        self::assertSame(409, $problem['status']);
    }

    public function testThePayloadMustReferToTheGame(): void
    {
        $scenario = $this->scenario2Bots();

        $problem = $this->problem($this->callTool('lose_honor', [
            'game_id' => $scenario->game()->getId(),
            'target_player_id' => 'unknown',
            'honor_loss' => 1,
        ]));

        self::assertSame(422, $problem['status']);
    }

    public function testTheBotMustBeAPlayerOfTheGame(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('hida-bot')
            ->withPlayer('john')
            ->persist();

        $problem = $this->problem($this->callTool('pass_opportunity', ['game_id' => $scenario->game()->getId()]));

        self::assertSame('not_a_player', $problem['type']);
    }

    public function testTheArgumentsMustMatchTheSchema(): void
    {
        $scenario = $this->scenario2Bots();

        $response = $this->request('tools/call', ['name' => 'pass_opportunity', 'arguments' => [
            'game_id' => $scenario->game()->getId(),
            'player_id' => $scenario->player('hida-bot')->getId(),
        ]]);

        self::assertArrayHasKey('error', $response);
    }
}
