<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Message\ActionInterface;
use App\Repository\GameRepository;
use App\Service\ProvinceRefiller;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * After the message handling, refills the provinces dynasty cards have left once nothing is being resolved
 * (ProvinceRefiller), before the automatic transitions (WorkflowTransitionMiddleware).
 */
readonly class ProvinceRefillMiddleware implements MiddlewareInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private ProvinceRefiller $provinceRefiller,
    ) {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()->handle($envelope, $stack);

        $message = $envelope->getMessage();

        if ($message instanceof ActionInterface) {
            $this->provinceRefiller->refillScheduled($this->gameRepository->get($message->getGameId()));
        }

        return $envelope;
    }
}
