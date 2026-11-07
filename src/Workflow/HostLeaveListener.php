<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Service\SetAsideStacks;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\LeaveEvent;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;

/**
 * The cards under a card (PlaceCardUnder) are discarded when it leaves its place (a character or an attachment leaving
 * play, a holding leaving its province or turned face down…), and their stack is removed: they stay with a card that
 * keeps its place (bowed, readied, its control taken, a holding moved to another province face up). Daidoji Uji
 * removes them from the game instead: an interrupt of the players (RemoveFromGame), before the card leaves. Provinces
 * and strongholds never leave theirs.
 */
#[AsEventListener(event: 'workflow.leave')]
readonly class HostLeaveListener
{
    public function __construct(
        private SetAsideStacks $setAsideStacks,
        private Registry $workflowRegistry,
    ) {
    }

    /**
     * @param LeaveEvent<object> $event
     */
    public function __invoke(LeaveEvent $event): void
    {
        $card = $event->getSubject();
        $transition = $event->getTransition();
        if (! ($card instanceof ConflictCard || $card instanceof DynastyCard)
            || ! $transition instanceof Transition
            || in_array($card->getCurrentPlace(), $transition->getTos(), true)) {
            return;
        }

        $stack = $this->setAsideStacks->findUnderneath($card);
        if ($stack === null) {
            return;
        }

        foreach ($stack->getOrderedCards() as $under) {
            $this->workflowRegistry->get($under)->apply($under, 'discard_card');
        }

        $this->setAsideStacks->remove($stack);
    }
}
