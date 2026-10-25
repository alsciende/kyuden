<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\PhysicalCard;
use App\Enum\Orientation;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * Bowing and readying keep the card in the same place (WorkflowConfig::bowAndReady()): only a ready card can be bowed
 * and only a bowed card can be readied.
 */
#[AsEventListener(event: 'workflow.guard')]
readonly class BowAndReadyGuardListener
{
    /**
     * @param GuardEvent<object> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        $card = $event->getSubject();
        if (! $card instanceof PhysicalCard) {
            return;
        }

        $transition = $event->getTransition()->getName();
        if ($transition === 'bow' && $card->getOrientation() !== Orientation::FaceUp) {
            $event->setBlocked(true, 'Only a ready card can be bowed.');
        }

        if ($transition === 'ready' && $card->getOrientation() !== Orientation::Sideways) {
            $event->setBlocked(true, 'Only a bowed card can be readied.');
        }
    }
}
