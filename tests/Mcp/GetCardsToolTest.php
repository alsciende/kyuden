<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Mcp\GetCardsTool;

class GetCardsToolTest extends AbstractMcpTestCase
{
    public function testTheBotReadsCardsByCode(): void
    {
        $result = $this->callTool('get_cards', ['codes' => ['01-above-question', '01-above-question']]);

        self::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertIsArray($result['structuredContent']);
        $cards = $result['structuredContent']['cards'];
        self::assertIsArray($cards);
        self::assertCount(1, $cards);
        self::assertIsArray($cards[0]);
        self::assertSame('Above Question', $cards[0]['name']);
    }

    public function testEveryCardMustExist(): void
    {
        $result = $this->callTool('get_cards', ['codes' => ['01-above-question', 'unknown']]);

        self::assertTrue($result['isError']);
        /** @var list<array{text: string}> $content */
        $content = $result['content'];
        self::assertSame('Card not found: [unknown]', $content[0]['text']);
    }

    public function testTheNumberOfCodesIsBounded(): void
    {
        $response = $this->request('tools/call', [
            'name' => 'get_cards',
            'arguments' => ['codes' => array_fill(0, GetCardsTool::MAX_CODES + 1, '01-above-question')],
        ]);

        self::assertArrayHasKey('error', $response);
    }
}
