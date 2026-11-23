<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Data\DataException;
use App\Exception\Data\PlayerNotFoundException;
use App\Exception\Rules\RulesException;
use App\Message\ActionInterface;
use App\Message\PlayerActionInterface;
use App\Middleware\Stamp\InterruptsResolvedStamp;
use App\Repository\GameRepository;
use App\Service\Interrupt\ImminentTriggers;
use App\Service\Interrupt\InterruptOfferer;
use App\Service\Interrupt\InterruptWindowService;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\InterruptedTransition;
use App\State\Model\InterruptWindow;
use App\State\Model\PlayerState;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Workflow\Exception\ExceptionInterface as WorkflowException;

/**
 * Splits a message between its initiation and its resolution, for the interrupts (doc/interrupts.md): right before
 * its handler, the conditions it would make occur (ImminentTriggers) open an interrupt window when some player is
 * asked (InterruptOfferer), and the message waits in it instead of being handled. While a window is open, only what
 * answers it is accepted (InterruptWindowService::assertAccepted()).
 *
 * After each message, the windows it has moved on are closed, the last one opened first: what waited in each is done,
 * a message handled at once (its handler, without the middlewares: it is recorded already), an automatic transition
 * left to WorkflowTransitionMiddleware. A message whose conditions were all cancelled is not done, unless cancelling
 * them means cancelling its effects (the effects of an event): it is done, its resolutions marked cancelled. A message
 * that its handler now refuses (what it named has gone elsewhere meanwhile) is not done.
 */
final readonly class InterruptWindowMiddleware implements MiddlewareInterface
{
    public function __construct(
        private GameRepository $gameRepository,
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
        private ImminentTriggers $imminentTriggers,
        private InterruptOfferer $interruptOfferer,
        private InterruptWindowService $interruptWindowService,
        private SerializerInterface $serializer,
        #[Autowire(service: 'default.bus.middleware.handle_message')]
        private MiddlewareInterface $handleMessageMiddleware,
    ) {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        // the game does not exist yet when it is created
        $game = $message instanceof ActionInterface ? $this->gameRepository->find($message->getGameId()) : null;
        if (! $game instanceof Game || $envelope->last(InterruptsResolvedStamp::class) instanceof InterruptsResolvedStamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        $player = null;
        if ($message instanceof PlayerActionInterface) {
            try {
                $player = $game->getPlayer($message->getPlayerId());
            } catch (PlayerNotFoundException) {
                // not a player of the game yet (JoinGame), or not at all: its handler says so
            }
        }

        $this->interruptWindowService->assertAccepted($game, $player, $message::class);

        $triggers = $this->imminentTriggers->resolveMessage($message, $game);
        if ($triggers !== [] && $this->interruptOfferer->open($game, $triggers, $message)) {
            return $envelope;
        }

        $envelope = $stack->next()->handle($envelope, $stack);
        $this->closeWindows($game);

        return $envelope;
    }

    private function closeWindows(Game $game): void
    {
        while (($window = $this->interruptWindowService->closeIfDone($game)) instanceof InterruptWindow) {
            if ($window->transition !== null) {
                $this->gameStateManager->withState($game, function (GameState $state) use ($window): GameState {
                    $state->interruptedTransition = new InterruptedTransition((string) $window->transition, $window->cancelledCardIds);

                    return $state;
                });
            }

            if ($window->messageName !== null && $window->messagePayload !== null && ($window->triggers !== [] || $window->cancelledEffectCardIds !== [])) {
                $message = $this->resolve($window->messageName, $window->messagePayload);
                if ($message instanceof PlayerActionInterface) {
                    $this->cancelResolutions($game->getPlayer($message->getPlayerId()), $window->cancelledEffectCardIds);
                }
            }
        }
    }

    /**
     * @return object|null the message, null when its handler has refused it
     */
    private function resolve(string $name, string $payload): ?object
    {
        $message = $this->serializer->deserialize($payload, $name, 'json');
        \assert(is_object($message));

        try {
            $this->handleMessageMiddleware->handle(new Envelope($message, [new InterruptsResolvedStamp()]), new StackMiddleware());
        } catch (HandlerFailedException $exception) {
            foreach ($exception->getWrappedExceptions() as $wrapped) {
                if (! $wrapped instanceof RulesException && ! $wrapped instanceof DataException && ! $wrapped instanceof WorkflowException) {
                    throw $exception;
                }
            }

            // refused now: what it named has gone elsewhere meanwhile
            return null;
        }

        return $message;
    }

    /**
     * The effects cancelled in the window: the resolutions the message has started are marked cancelled, its player
     * only ends them.
     *
     * @param list<string> $cardIds
     */
    private function cancelResolutions(Player $player, array $cardIds): void
    {
        if ($cardIds === []) {
            return;
        }

        $this->playerStateManager->withState($player, function (PlayerState $state) use ($cardIds): PlayerState {
            foreach ($cardIds as $cardId) {
                $state->cancelResolution($cardId);
            }

            return $state;
        });
    }
}
