<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

class GlossaryToolsTest extends AbstractMcpTestCase
{
    public function testTheBotListsTheEntries(): void
    {
        $result = $this->callTool('list_glossary_entries', []);

        self::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertIsArray($result['structuredContent']);
        self::assertIsArray($result['structuredContent']['titles']);
        self::assertContains('Bow, Bowed', $result['structuredContent']['titles']);
    }

    public function testTheBotReadsEntriesByTerm(): void
    {
        $result = $this->callTool('get_glossary_entries', ['terms' => ['bowed', 'Bow, Bowed', 'Covert']]);

        self::assertFalse($result['isError'] ?? false, json_encode($result, \JSON_THROW_ON_ERROR));
        self::assertIsArray($result['structuredContent']);
        /** @var list<array{title: string, text: string}> $entries */
        $entries = $result['structuredContent']['entries'];
        self::assertSame(['Bow, Bowed', 'Covert'], array_column($entries, 'title'));
    }

    public function testEveryEntryMustExist(): void
    {
        $result = $this->callTool('get_glossary_entries', ['terms' => ['Covert', 'unknown']]);

        self::assertTrue($result['isError']);
        /** @var list<array{text: string}> $content */
        $content = $result['content'];
        self::assertSame('Glossary entry not found: [unknown] (see list_glossary_entries)', $content[0]['text']);
    }
}
