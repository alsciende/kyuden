<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Event\DynastyPhaseBeginsEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsEnteredListener;
use Symfony\Component\Workflow\Event\EnteredEvent;

#[AsEnteredListener(workflow: 'game', place: 'reveal_facedown_dynasty_cards')]
readonly class RevealFacedownDynastyCardsEnteredListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(EnteredEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();
        $this->eventDispatcher->dispatch(
            new DynastyPhaseBeginsEvent($game->getId())
        );
    }
}
