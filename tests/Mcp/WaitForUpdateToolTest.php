<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\DataFixtures\UserFixtures;
use App\Message\LoseHonor;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tool through the MCP server, as an agent calls it: HTTP transport, authenticated with the API key of a bot.
 */
class WaitForUpdateToolTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

    private ?string $sessionId = null;

    /**
     * Akodo bot makes Hida bot lose 1 honor.
     */
    private function scenarioWithLog(): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('akodo-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('hida-bot', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->persist();
        $this->messageBus->dispatch(new LoseHonor(
            $scenario->game()->getId(),
            $scenario->player('akodo-bot')->getId(),
            $scenario->player('hida-bot')->getId(),
            1,
        ));

        return $scenario;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function post(array $message, string $user = 'akodo-bot'): Response
    {
        $client = self::getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->disableReboot();

        $client->request('POST', '/api/mcp', server: array_filter([
            'HTTP_AUTHORIZATION' => 'Bearer ' . UserFixtures::TOKENS[$user],
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_SESSION_ID' => $this->sessionId,
        ]), content: json_encode(['jsonrpc' => '2.0', ...$message], \JSON_THROW_ON_ERROR));

        return $client->getResponse();
    }

    /**
     * Opens a session of the MCP server.
     */
    private function initialize(): void
    {
        $response = $this->post(['id' => 1, 'method' => 'initialize', 'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1'],
        ]]);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->sessionId = $response->headers->get('Mcp-Session-Id');
        $this->post(['method' => 'notifications/initialized']);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<mixed> the result of the call
     */
    private function call(array $arguments): array
    {
        $this->initialize();
        $response = $this->post(['id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'wait_for_update', 'arguments' => $arguments]]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);
        self::assertIsArray($content['result'] ?? null, (string) $response->getContent());

        return $content['result'];
    }

    public function testTheBotReadsTheLogSinceTheVersionItKnows(): void
    {
        $scenario = $this->scenarioWithLog();

        $result = $this->call(['game_id' => $scenario->game()->getId(), 'since' => 0]);

        self::assertFalse($result['isError'] ?? false);
        self::assertIsArray($result['structuredContent']);
        $log = $result['structuredContent'];
        self::assertSame(1, $log['version']);
        // the keys of the params are not in the order of the template
        self::assertEquals([[
            'version' => 1,
            'type' => 'lose_honor',
            'player_id' => $scenario->player('akodo-bot')->getId(),
            'entries' => [[
                'template' => '{player} loses {amount} honor',
                'params' => [
                    'player' => ['type' => 'player', 'id' => $scenario->player('hida-bot')->getId()],
                    'amount' => ['type' => 'value', 'value' => 1, 'hidden' => false],
                ],
            ]],
        ]], $log['messages']);
    }

    public function testTheBotGetsNoMessageWhenTheGameHasNotChangedInTime(): void
    {
        $scenario = $this->scenarioWithLog();
        $clock = self::mockTime();
        $start = (float) $clock->now()->format('U.u');

        $result = $this->call(['game_id' => $scenario->game()->getId(), 'since' => 1, 'timeout' => 5]);

        self::assertIsArray($result['structuredContent']);
        self::assertSame(1, $result['structuredContent']['version']);
        self::assertSame([], $result['structuredContent']['messages']);
        self::assertSame(5.0, round((float) $clock->now()->format('U.u') - $start, 3));
    }

    public function testTheBotMustBeAPlayerOfTheGame(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('hida-bot')
            ->withPlayer('john')
            ->persist();

        $result = $this->call(['game_id' => $scenario->game()->getId()]);

        self::assertTrue($result['isError']);
    }

    public function testTheGameMustExist(): void
    {
        $result = $this->call(['game_id' => 'unknown']);

        self::assertTrue($result['isError']);
    }

    public function testTheTimeoutIsBounded(): void
    {
        $scenario = $this->scenarioWithLog();
        $this->initialize();

        $response = $this->post(['id' => 2, 'method' => 'tools/call', 'params' => [
            'name' => 'wait_for_update',
            'arguments' => ['game_id' => $scenario->game()->getId(), 'timeout' => 31],
        ]]);

        self::assertStringContainsString('"error"', (string) $response->getContent());
    }

    public function testAHumanCannotUseTheMcpServer(): void
    {
        $response = $this->post(['id' => 1, 'method' => 'tools/list'], 'john');

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
}
