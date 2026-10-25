<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Registry;

/**
 * 4.2: in player order, each player discards each character they control with no fate on it. The engine chooses the
 * order of the characters of a player.
 */
#[AsTransitionListener(workflow: 'game', transition: 'characters_discarded')]
readonly class CharactersDiscardedTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private CardStateManager $cardStateManager,
        private Registry $workflowRegistry,
    ) {
    }

    /**
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();

        foreach ($this->stateManager->getState($game)->playerIdsFromFirstPlayer() as $playerId) {
            foreach ($game->getPlayer($playerId)->getCharactersInPlay() as $character) {
                if ($this->cardStateManager->getState($character)->getFate() === 0) {
                    $this->workflowRegistry->get($character)->apply($character, 'discard_card');
                }
            }
        }
    }
}
