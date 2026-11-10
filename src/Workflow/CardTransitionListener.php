<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\PhysicalCard;
use App\Entity\Stack;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Enum\Type;
use App\Factory\StackFactory;
use App\Service\ControlService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * Applies the move described in the metadata of a card transition:
 * - `stack`: the StackType the card moves to. The target Stack is:
 *   - for a card entering play: the Stack given in the `stack` key of the transition context (the stack of the
 *     character or province an attachment is put on, a character played as an attachment included), else a new stack
 *     of its controller for a character, or for a card the transition turns into one (metadata `character`: detach);
 *   - for a unique stack type: the stack given in the context, or else the player's stack;
 *   - for another stack type: the stack given in the context.
 *   Whether the card can be in the target stack is checked by CardMarkingStore.
 * - `orientation`: the Orientation the card takes. Required when the card moves; without it, the card keeps
 *   its orientation.
 * - `position`: `bottom` puts the card at the bottom of the target stack; on top of it otherwise.
 *
 * The workflows are defined in config/packages/workflows.php.
 */
#[AsEventListener(event: 'workflow.transition')]
readonly class CardTransitionListener
{
    public function __construct(
        private StackFactory $stackFactory,
        private ControlService $controlService,
    ) {
    }

    /**
     * @param TransitionEvent<object> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $card = $event->getSubject();
        if (! $card instanceof PhysicalCard) {
            return;
        }

        $stackType = $event->getMetadata('stack', $event->getTransition());
        $orientation = $event->getMetadata('orientation', $event->getTransition());

        if ($stackType instanceof StackType) {
            if (! $orientation instanceof Orientation) {
                throw new \LogicException(sprintf("Transition %s moves the card: it must give its 'orientation' metadata.", $event->getTransition()?->getName()));
            }

            // on top of the stack, unless the transition puts it at the bottom (a card can move within its stack)
            $above = $event->getMetadata('position', $event->getTransition()) !== 'bottom';
            $stack = $this->getTargetStack($card, $stackType, $event->getContext(), $event->getMetadata('character', $event->getTransition()) === true);
            if ($card->getStack() === $stack) {
                $stack->moveCard($card, $above);
            } else {
                $stack->addCard($card, $above);
            }
        }

        if ($orientation instanceof Orientation) {
            $card->setOrientation($orientation);
        }
    }

    /**
     * @param array<mixed> $context
     */
    private function getTargetStack(PhysicalCard $card, StackType $stackType, array $context, bool $asCharacter): Stack
    {
        $stack = $context['stack'] ?? null;
        if ($stack !== null && ! $stack instanceof Stack) {
            throw new \LogicException("The 'stack' key of the transition context must be a Stack.");
        }

        if ($stack !== null) {
            return $stack;
        }

        if ($stackType === StackType::InPlay && ($card->getLogicalCard()->getType() === Type::Character || $asCharacter)) {
            return $this->stackFactory->createStack($this->controlService->getController($card), $stackType);
        }

        if ($stackType->isUnique()) {
            return $card->getPlayer()->getUniqueStack($stackType);
        }

        throw new \LogicException(sprintf("Card %s needs a target stack in the 'stack' key of the transition context.", $card->getId()));
    }
}
