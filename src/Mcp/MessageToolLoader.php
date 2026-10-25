<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Service\Api\MessageSchemaFactory;
use App\Service\Api\PlayerMessageSender;
use Mcp\Capability\Registry\Loader\ExplicitElementLoader;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * One tool per public action message, named after its type (`pass_opportunity`): its arguments are the payload of the
 * message (the schema of the catalogue, see MessageSchemaFactory), the id of the game and the version the agent saw.
 * The tool sends the message as the player of the bot in the game (see MessageTool).
 */
final readonly class MessageToolLoader implements LoaderInterface
{
    public function __construct(
        private MessageSchemaFactory $messageSchemaFactory,
        private PlayerMessageSender $messageSender,
        private Security $security,
    ) {
    }

    #[\Override]
    public function load(RegistryInterface $registry): void
    {
        $tools = [];
        foreach ($this->messageSchemaFactory->createAll() as $type => $schema) {
            $tools[] = [
                'definition' => new Tool(
                    name: $type,
                    title: null,
                    inputSchema: $this->createInputSchema($schema),
                    description: (\is_string($schema['description'] ?? null) ? $schema['description'] . "\n\n" : '')
                        . 'Sent by you, as your player in the game. Returns the version of the game once the action is handled.',
                    annotations: new ToolAnnotations(readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false),
                    outputSchema: [
                        'type' => 'object',
                        'properties' => ['version' => ['type' => 'integer', 'description' => 'The version of the game once the action is handled.']],
                        'required' => ['version'],
                    ],
                ),
                'handler' => new MessageTool($type, $this->messageSender, $this->security),
            ];
        }

        new ExplicitElementLoader(tools: $tools)->load($registry);
    }

    /**
     * The schema of the payload, with the id of the game and the version the agent saw.
     *
     * @param array<string, mixed> $schema
     *
     * @return array{type: 'object', properties: array<string, mixed>, required: list<string>, additionalProperties: false}
     */
    private function createInputSchema(array $schema): array
    {
        // an object, so that a message without field is encoded `{}`
        /** @var array<string, mixed> $properties the names of the fields */
        $properties = \is_object($schema['properties'] ?? null) ? get_object_vars($schema['properties']) : [];
        $required = \is_array($schema['required'] ?? null) ? array_values(array_filter($schema['required'], \is_string(...))) : [];

        return [
            'type' => 'object',
            'properties' => [
                MessageTool::GAME_ID => ['description' => 'Id of the game.', 'type' => 'string'],
                MessageTool::EXPECTED_VERSION => [
                    'description' => 'Version of the game you saw (wait_for_update): the action is rejected if the game has changed since.',
                    'type' => 'integer',
                    'minimum' => 0,
                ],
                ...$properties,
            ],
            'required' => [MessageTool::GAME_ID, ...$required],
            'additionalProperties' => false,
        ];
    }
}
