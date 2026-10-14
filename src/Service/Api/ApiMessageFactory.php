<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Message\ActionInterface;
use App\Message\PlayerActionInterface;
use App\Service\ActionMessageRegistry;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Builds the message a player sends through the API, from its type (snake case short name of the message class) and
 * its payload (its constructor arguments in snake case, except the game and the player).
 *
 * The game comes from the URL and the player, who sends the message, is the authenticated player.
 */
readonly class ApiMessageFactory
{
    private const string GAME_ID = 'game_id';
    private const string PLAYER_ID = 'player_id';

    public function __construct(
        private ActionMessageRegistry $actionMessageRegistry,
        private DenormalizerInterface $denormalizer,
    ) {
    }

    /**
     * @param array<mixed> $payload
     *
     * @throws BadRequestHttpException   unknown type or invalid payload
     * @throws AccessDeniedHttpException message sent on behalf of another player
     */
    public function create(string $type, array $payload, string $gameId, string $playerId): ActionInterface
    {
        $className = $this->actionMessageRegistry->findMessageClass($type);
        if ($className === null || ! $this->actionMessageRegistry->getAttribute($className)->public) {
            throw new BadRequestHttpException("Unknown message type: {$type}.");
        }

        if (isset($payload[self::GAME_ID]) && $payload[self::GAME_ID] !== $gameId) {
            throw new BadRequestHttpException('The game of the payload is not the game of the URL.');
        }

        $payload[self::GAME_ID] = $gameId;

        if (is_a($className, PlayerActionInterface::class, true)) {
            if (isset($payload[self::PLAYER_ID]) && $payload[self::PLAYER_ID] !== $playerId) {
                throw new AccessDeniedHttpException('A player cannot send this message on behalf of another player.');
            }

            $payload[self::PLAYER_ID] = $playerId;
        }

        try {
            $message = $this->denormalizer->denormalize($payload, $className, 'json', [
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
            ]);
        } catch (SerializerException $exception) {
            throw new BadRequestHttpException("Invalid payload for {$type}: {$exception->getMessage()}", $exception);
        }

        if (! $message instanceof ActionInterface) {
            throw new \LogicException("Message class {$className} does not implement " . ActionInterface::class);
        }

        return $message;
    }
}
