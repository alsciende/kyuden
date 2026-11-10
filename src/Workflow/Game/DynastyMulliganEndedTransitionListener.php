<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Message\DrawConflictCards;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * Setup 7: once the dynasty cards are mulliganed, each player draws their starting hand.
 */
#[AsTransitionListener(workflow: 'game', transition: 'dynasty_mulligan_ended')]
readonly class DynastyMulliganEndedTransitionListener
{
    public const int STARTING_CARDS = 4;

    public function __construct(
        private MessageBusInterface $transitionBus,
    ) {
    }

    /**
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            $this->transitionBus->dispatch(
                new DrawConflictCards($game->getId(), $player->getId(), $player->getId(), self::STARTING_CARDS)
            );
        }
    }
}
