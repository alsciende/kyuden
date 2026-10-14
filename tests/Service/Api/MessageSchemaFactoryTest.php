<?php

declare(strict_types=1);

namespace App\Tests\Service\Api;

use App\Command\MessageSchemasDumpCommand;
use App\Service\Api\MessageSchemaFactory;
use App\Tests\AbstractKernelTestCase;

class MessageSchemaFactoryTest extends AbstractKernelTestCase
{
    /**
     * The snapshot of the contract of the API: the schemas built from the messages must match the committed files.
     * After a deliberate change of a public message, run `bin/console app:message-schemas:dump`.
     */
    public function testTheSchemasMatchTheSnapshot(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);
        $directory = "{$projectDir}/" . MessageSchemasDumpCommand::DIRECTORY;
        $schemas = self::service(MessageSchemaFactory::class)->createAll();

        $files = array_map(fn (string $path): string => basename($path, '.json'), glob("{$directory}/*.json") ?: []);
        self::assertSame(array_keys($schemas), $files, 'Run bin/console app:message-schemas:dump.');
        foreach ($schemas as $type => $schema) {
            self::assertSame(file_get_contents("{$directory}/{$type}.json"), MessageSchemaFactory::encode($schema), "Run bin/console app:message-schemas:dump ({$type}).");
        }
    }

    public function testEveryPublicMessageIsDescribed(): void
    {
        foreach (self::service(MessageSchemaFactory::class)->createAll() as $type => $schema) {
            self::assertNotEmpty($schema['description'] ?? null, "{$type} has no class doc comment.");
            self::assertIsObject($schema['properties']);
            foreach ((array) $schema['properties'] as $name => $property) {
                self::assertIsArray($property);
                self::assertNotEmpty($property['description'] ?? null, "{$type}.{$name} has no Description attribute.");
            }
        }
    }

    public function testThePayloadExcludesTheGameAndThePlayer(): void
    {
        $schema = self::service(MessageSchemaFactory::class)->createAll()['lose_honor'];

        self::assertSame(['target_player_id', 'honor_loss'], $schema['required']);
        self::assertFalse($schema['additionalProperties']);
        self::assertEquals((object) [
            'target_player_id' => ['description' => 'Player who loses honor.', 'type' => 'string'],
            'honor_loss' => ['description' => 'Honor lost.', 'type' => 'integer', 'minimum' => 0],
        ], $schema['properties']);
    }

    public function testPlatformMessagesHaveNoSchema(): void
    {
        $schemas = self::service(MessageSchemaFactory::class)->createAll();

        self::assertArrayNotHasKey('create_game', $schemas);
        self::assertArrayNotHasKey('join_game', $schemas);
    }
}
