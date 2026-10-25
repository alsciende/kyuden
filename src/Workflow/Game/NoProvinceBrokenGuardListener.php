<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * The players react to a broken province only when one is: otherwise the conflict goes on with the ring effects.
 */
#[AsGuardListener(workflow: 'game', transition: 'no_province_broken')]
readonly class NoProvinceBrokenGuardListener
{
    public function __construct(
        private GameStateManager $stateManager,
    ) {
    }

    /**
     * @param GuardEvent<Game> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        $game = $event->getSubject();

        if ($this->stateManager->getState($game)->conflict?->brokenProvinceIds !== []) {
            $event->setBlocked(true, 'A province is broken: the players may react to it.');
        }
    }
}
