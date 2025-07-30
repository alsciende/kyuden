<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Entity\Game;
use App\Message\ActionInterface;
use App\Repository\GameRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Check if a transition can be applied automatically after the message handling.
 */
readonly class WorkflowTransitionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private WorkflowInterface $gameStateMachine,
        private GameRepository $gameRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()->handle($envelope, $stack);

        $message = $envelope->getMessage();

        if ($message instanceof ActionInterface) {
            $gameId = $message->getGameId();
            $game = $this->gameRepository->get($gameId);
            do {
                $transitionOccurred = $this->checkTransition($game);
            } while ($transitionOccurred);
        }

        return $envelope;
    }

    /**
     * @return bool True if a transition occurred
     */
    private function checkTransition(Game $game): bool
    {
        $availableTransitions = $this->gameStateMachine->getEnabledTransitions($game);
        $this->logger->debug('WorkflowTransitionMiddleware', [
            'game_id' => $game->getId(),
            'available_transitions' => count($availableTransitions),
        ]);

        foreach ($availableTransitions as $transition) {
            $isAuto = $this->gameStateMachine->getMetadataStore()->getMetadata('auto', $transition);
            $this->logger->debug('available_transition', [
                'transition' => $transition->getName(),
                'auto' => $isAuto,
            ]);

            if ($isAuto) {
                $this->gameStateMachine->apply($game, $transition->getName());

                return true;
            }
        }

        return false;
    }
}
