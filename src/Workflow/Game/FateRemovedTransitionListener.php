<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\CardStateManager;
use App\State\Model\CardState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 4.3: 1 fate is removed from each character in play.
 */
#[AsTransitionListener(workflow: 'game', transition: 'fate_removed')]
readonly class FateRemovedTransitionListener
{
    public const int FATE_REMOVED = 1;

    public function __construct(
        private CardStateManager $cardStateManager,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            foreach ($player->getCharactersInPlay() as $character) {
                $this->cardStateManager->withState(
                    $character,
                    fn (CardState $state): CardState => $state->removeFate(min(self::FATE_REMOVED, $state->getFate())),
                );
            }
        }
    }
}
