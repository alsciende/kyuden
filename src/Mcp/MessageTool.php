<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Exception\Api\ApiProblemException;
use App\Service\Api\PlayerMessageSender;
use Mcp\Exception\ToolCallException;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The tool of an action message (see MessageToolLoader): sends the message as the player of the bot in the game. A
 * rejected message is a tool error whose text is the problem, as the HTTP API sends it (type, status, detail,
 * violations).
 */
final readonly class MessageTool implements ToolHandlerInterface
{
    public const string GAME_ID = 'game_id';

    public const string EXPECTED_VERSION = 'expected_version';

    public function __construct(
        private string $type,
        private PlayerMessageSender $messageSender,
        private Security $security,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{version: int}
     */
    #[\Override]
    public function execute(array $arguments, ClientGateway $gateway): array
    {
        // the server checks the arguments against their schema before the call
        $gameId = $arguments[self::GAME_ID];
        $expectedVersion = $arguments[self::EXPECTED_VERSION] ?? null;
        \assert(\is_string($gameId) && ($expectedVersion === null || \is_int($expectedVersion)));
        unset($arguments[self::GAME_ID], $arguments[self::EXPECTED_VERSION]);

        $user = $this->security->getUser();
        \assert($user !== null);

        try {
            return ['version' => $this->messageSender->send($gameId, $user->getUserIdentifier(), $this->type, $arguments, $expectedVersion)];
        } catch (ApiProblemException $problem) {
            throw new ToolCallException(json_encode($problem->toArray(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), previous: $problem);
        }
    }
}
