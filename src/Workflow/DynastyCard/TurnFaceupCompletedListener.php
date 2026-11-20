<?php

declare(strict_types=1);

namespace App\Workflow\DynastyCard;

use App\Entity\CardTypes\DynastyCard;
use App\Event\DynastyCardTurnedFaceupEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Once a dynasty card is face up in its province, the cards reacting to it may be triggered
 * (DynastyCardTurnedFaceupEvent).
 */
#[AsCompletedListener(workflow: 'dynasty_card', transition: 'turn_faceup')]
readonly class TurnFaceupCompletedListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param CompletedEvent<DynastyCard> $event
     */
    public function __invoke(CompletedEvent $event): void
    {
        $card = $event->getSubject();

        $this->eventDispatcher->dispatch(new DynastyCardTurnedFaceupEvent($card->getGame(), $card));
    }
}
