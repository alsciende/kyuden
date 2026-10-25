<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Entity\Game;
use App\Message\ActionInterface;
use App\Repository\GameRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
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
        #[Target('game')]
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
     * Applies the automatic transition with the highest `priority` (0 by default). Two enabled automatic transitions
     * with the same highest priority are a definition error.
     *
     * @return bool True if a transition occurred
     */
    private function checkTransition(Game $game): bool
    {
        $availableTransitions = $this->gameStateMachine->getEnabledTransitions($game);
        $this->logger->debug('WorkflowTransitionMiddleware', [
            'game_id' => $game->getId(),
            'available_transitions' => count($availableTransitions),
        ]);

        $metadataStore = $this->gameStateMachine->getMetadataStore();
        $autoTransitions = [];
        foreach ($availableTransitions as $transition) {
            $isAuto = $metadataStore->getMetadata('auto', $transition);
            $this->logger->debug('available_transition', [
                'transition' => $transition->getName(),
                'auto' => $isAuto,
            ]);

            if ($isAuto) {
                $priority = $metadataStore->getMetadata('priority', $transition) ?? 0;
                \assert(is_int($priority));
                $autoTransitions[$priority][] = $transition->getName();
            }
        }

        if ($autoTransitions === []) {
            return false;
        }

        krsort($autoTransitions);
        $highest = reset($autoTransitions);
        if (count($highest) > 1) {
            throw new \LogicException(sprintf('Ambiguous automatic transitions with the same priority: %s.', implode(', ', $highest)));
        }

        $this->gameStateMachine->apply($game, $highest[0]);

        return true;
    }
}
