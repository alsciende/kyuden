<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\Exception\Api\InvalidPayloadException;
use App\Message\ActionInterface;
use App\Message\PlayerActionInterface;
use App\Service\ActionMessageRegistry;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

use function Symfony\Component\String\u;

/**
 * Builds the message a player sends through the API, from its type (snake case short name of the message class) and
 * its payload (its constructor arguments in snake case, except the game and the player).
 *
 * The game comes from the URL and the player, who sends the message, is the authenticated player: the payload cannot
 * hold them. The values of the message are checked afterwards by the Validator (validation middleware of the bus).
 */
readonly class ApiMessageFactory
{
    public const string GAME_ID = 'game_id';
    public const string PLAYER_ID = 'player_id';

    public function __construct(
        private ActionMessageRegistry $actionMessageRegistry,
        private DenormalizerInterface $denormalizer,
    ) {
    }

    /**
     * @param array<mixed> $payload
     *
     * @throws BadRequestHttpException unknown type
     * @throws InvalidPayloadException payload not matching the message
     */
    public function create(string $type, array $payload, string $gameId, string $playerId): ActionInterface
    {
        $className = $this->actionMessageRegistry->findMessageClass($type);
        if ($className === null || ! $this->actionMessageRegistry->getAttribute($className)->public) {
            throw new BadRequestHttpException("Unknown message type: {$type}.");
        }

        $reserved = array_values(array_intersect([self::GAME_ID, self::PLAYER_ID], array_keys($payload)));
        if ($reserved !== []) {
            throw new InvalidPayloadException($type, array_map(
                fn (string $field): array => ['property_path' => $field, 'message' => 'This field is set by the server and cannot be sent.'],
                $reserved,
            ));
        }

        $payload[self::GAME_ID] = $gameId;
        if (is_a($className, PlayerActionInterface::class, true)) {
            $payload[self::PLAYER_ID] = $playerId;
        }

        try {
            $message = $this->denormalizer->denormalize($payload, $className, 'json', [
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);
        } catch (PartialDenormalizationException $exception) {
            throw new InvalidPayloadException($type, $this->getViolations($exception));
        } catch (ExtraAttributesException $exception) {
            throw new InvalidPayloadException($type, array_map(
                fn (mixed $attribute): array => ['property_path' => (string) $attribute, 'message' => 'This field is not expected.'],
                array_values($exception->getExtraAttributes()),
            ));
        } catch (SerializerException $exception) {
            throw new InvalidPayloadException($type, [['property_path' => '', 'message' => $exception->getMessage()]]);
        }

        if (! $message instanceof ActionInterface) {
            throw new \LogicException("Message class {$className} does not implement " . ActionInterface::class);
        }

        return $message;
    }

    /**
     * @return list<array{property_path: string, message: string}>
     */
    private function getViolations(PartialDenormalizationException $exception): array
    {
        $violations = [];
        foreach ($exception->getErrors() as $error) {
            // missing constructor arguments are reported by their name in the class, in camel case
            $violations[] = ['property_path' => u($error->getPath() ?? '')->snake()->toString(), 'message' => $error->getMessage()];
        }

        return $violations;
    }
}
