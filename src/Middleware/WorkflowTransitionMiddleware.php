<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Entity\Game;
use App\Message\ActionInterface;
use App\Repository\GameRepository;
use App\Service\Interrupt\ImminentTriggers;
use App\Service\Interrupt\InterruptOfferer;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * After the message handling, applies the automatic transitions, one at a time, until none is enabled. While an
 * interrupt window is open, only the end of the game: the rest waits for the window. A transition making triggering
 * conditions occur (metadata `interrupt`) first opens their interrupt window, when some player is asked
 * (InterruptOfferer), and waits for it to close (GameState::interruptedTransition).
 */
readonly class WorkflowTransitionMiddleware implements MiddlewareInterface
{
    private const string END_GAME = 'end_game';

    public function __construct(
        #[Target('game')]
        private WorkflowInterface $gameStateMachine,
        private GameRepository $gameRepository,
        private GameStateManager $gameStateManager,
        private ImminentTriggers $imminentTriggers,
        private InterruptOfferer $interruptOfferer,
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
        $state = $this->gameStateManager->getState($game);
        $windowOpen = $state->interruptWindows !== [];
        $availableTransitions = $this->gameStateMachine->getEnabledTransitions($game);
        $this->logger->debug('WorkflowTransitionMiddleware', [
            'game_id' => $game->getId(),
            'available_transitions' => count($availableTransitions),
        ]);

        $metadataStore = $this->gameStateMachine->getMetadataStore();
        $autoTransitions = [];
        $transitions = [];
        foreach ($availableTransitions as $transition) {
            $transitions[$transition->getName()] = $transition;
            $isAuto = $metadataStore->getMetadata('auto', $transition);
            $this->logger->debug('available_transition', [
                'transition' => $transition->getName(),
                'auto' => $isAuto,
            ]);

            if ($isAuto && (! $windowOpen || $transition->getName() === self::END_GAME)) {
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

        $name = $highest[0];
        $interrupt = $metadataStore->getMetadata('interrupt', $transitions[$name]) === true;
        if ($interrupt && $state->interruptedTransition?->name !== $name) {
            $triggers = $this->imminentTriggers->resolveTransition($name, $game);
            if ($triggers !== [] && $this->interruptOfferer->open($game, $triggers, transition: $name)) {
                return false;
            }
        }

        $this->gameStateMachine->apply($game, $name);
        if ($this->gameStateManager->getState($game)->interruptedTransition !== null) {
            $this->gameStateManager->withState($game, function (GameState $state): GameState {
                $state->interruptedTransition = null;

                return $state;
            });
        }

        return true;
    }
}
