<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Entity\Event;
use App\Message\ActionInterface;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Record the message as an Event after its handling, and increment the version of the game.
 */
readonly class EventRecordingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private GameRepository $gameRepository,
    ) {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // forward message and use new envelope
        $envelope = $stack->next()->handle($envelope, $stack);

        $message = $envelope->getMessage();

        if ($message instanceof ActionInterface) {
            $this->recordAction($message);
        }

        return $envelope;
    }

    private function recordAction(ActionInterface $action): void
    {
        // the game is locked (GameLockMiddleware) or has just been created by the handler
        $game = $this->gameRepository->get($action->getGameId());
        $name = $action::class;
        $payload = $this->serializer->serialize($action, 'json');

        $this->entityManager->persist(new Event($game, $game->incrementVersion(), $name, $payload));
    }
}
