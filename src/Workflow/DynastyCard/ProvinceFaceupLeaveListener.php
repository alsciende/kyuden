<?php

declare(strict_types=1);

namespace App\Workflow\DynastyCard;

use App\Entity\CardTypes\DynastyCard;
use App\Event\HonorTokensRemovedEvent;
use App\State\Manager\CardStateManager;
use App\State\Model\CardState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsLeaveListener;
use Symfony\Component\Workflow\Event\LeaveEvent;
use Symfony\Component\Workflow\Transition;

/**
 * A holding face up in a province is in play (glossary, "Holding"): when it leaves play (discarded, sacrificed, turned
 * face down, returned to its deck…), the honor tokens on it return to the general token pool ("When a card with any
 * tokens or counters on it leaves play, those tokens and counters are returned to the token pool"). Moving to another
 * province keeps them.
 */
#[AsLeaveListener(workflow: 'dynasty_card', place: DynastyCard::STATE_PROVINCE_FACEUP)]
readonly class ProvinceFaceupLeaveListener
{
    public function __construct(
        private CardStateManager $cardStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param LeaveEvent<DynastyCard> $event
     */
    public function __invoke(LeaveEvent $event): void
    {
        $card = $event->getSubject();
        $transition = $event->getTransition();
        if (! $transition instanceof Transition || in_array(DynastyCard::STATE_PROVINCE_FACEUP, $transition->getTos(), true)) {
            return;
        }

        $honorTokens = $this->cardStateManager->getState($card)->getHonorTokens();
        if ($honorTokens === 0) {
            return;
        }

        $this->cardStateManager->withState($card, fn (CardState $state): CardState => $state->setHonorTokens(0));
        $this->eventDispatcher->dispatch(new HonorTokensRemovedEvent($card->getGame(), $card, $honorTokens));
    }
}
