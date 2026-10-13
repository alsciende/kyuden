<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Entity\Game;
use App\Exception\Rules\GameEndedException;
use App\Message\ActionInterface;
use App\Repository\GameRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Rejects every action on a game that has ended, before its handling.
 */
readonly class GameEndedMiddleware implements MiddlewareInterface
{
    public function __construct(
        private GameRepository $gameRepository,
    ) {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        // the game does not exist yet when it is created
        if ($message instanceof ActionInterface && $this->gameRepository->find($message->getGameId())?->getCurrentPlace() === Game::STATE_ENDED) {
            throw new GameEndedException();
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
