<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Mcp\RulesResources;
use Symfony\Component\HttpFoundation\Response;

class RulesResourcesTest extends AbstractMcpTestCase
{
    public function testTheServerTellsHowToPlay(): void
    {
        $response = $this->post(['id' => 0, 'method' => 'initialize', 'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1'],
        ]]);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($content);
        self::assertIsArray($content['result']);
        self::assertIsString($content['result']['instructions']);
        self::assertStringContainsString('expected_version', $content['result']['instructions']);
        self::assertStringContainsString(RulesResources::URI_PREFIX . 'learn_to_play', $content['result']['instructions']);
    }

    public function testTheRulesAreListed(): void
    {
        $response = $this->request('resources/list');

        self::assertIsArray($response['result']);
        self::assertIsArray($response['result']['resources']);
        $uris = array_column($response['result']['resources'], 'uri');
        sort($uris);
        self::assertSame([
            RulesResources::URI_PREFIX . 'card_clarifications',
            RulesResources::URI_PREFIX . 'learn_to_play',
            RulesResources::URI_PREFIX . 'timing_and_gameplay',
        ], $uris);
    }

    public function testTheBotReadsTheRules(): void
    {
        $response = $this->request('resources/read', ['uri' => RulesResources::URI_PREFIX . 'timing_and_gameplay']);

        self::assertIsArray($response['result']);
        /** @var list<array{uri: string, mimeType: string, text: string}> $contents */
        $contents = $response['result']['contents'];
        self::assertSame('text/markdown', $contents[0]['mimeType']);
        self::assertStringEqualsFile(\dirname(__DIR__, 2) . '/doc/timing-and-gameplay.md', $contents[0]['text']);
    }

    public function testThePromptIsOneLine(): void
    {
        $response = $this->request('prompts/get', ['name' => 'play', 'arguments' => ['deck' => 'crab-starter-deck']]);

        self::assertIsArray($response['result'], json_encode($response, \JSON_THROW_ON_ERROR));
        /** @var list<array{role: string, content: array{type: string, text: string}}> $messages */
        $messages = $response['result']['messages'];
        self::assertSame('user', $messages[0]['role']);
        self::assertSame(
            'Find your game on Kyūden and play it to the end. If you have to join the queue, use the deck crab-starter-deck.',
            $messages[0]['content']['text'],
        );
    }
}
