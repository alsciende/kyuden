<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Enum\HiddenPile;
use App\Service\CardInspector;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

/**
 * Setup 6 and 7 (glossary, Mulligan): the players mulligan in player order, starting with the first player, who is the
 * only active player; the handler of the mulligan gives the opportunity to the next player. In a game without mulligan
 * (the house bot), nobody is active: the step is passed at once (MulliganEndedGuardListener).
 *
 * Before the dynasty mulligan, each player looks at the cards of their provinces, which they may no longer look at
 * afterwards: the log gives them their cards, so that the second player sees them while the first one chooses.
 */
#[AsEnterListener(workflow: 'game', place: 'dynasty_mulligan')]
#[AsEnterListener(workflow: 'game', place: 'conflict_mulligan')]
readonly class MulliganEnterListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private CardInspector $cardInspector,
    ) {
    }

    /**
     * @param EnterEvent<Game> $event
     */
    public function __invoke(EnterEvent $event): void
    {
        $game = $event->getSubject();
        $mulligan = $this->stateManager->getState($game)->mulligan;

        $this->stateManager->withState($game, function (GameState $state) use ($mulligan): GameState {
            if ($mulligan) {
                return $state->activateFirstPlayer();
            }

            $state->activePlayerIds = [];

            return $state;
        });

        // the place is entered: the marking is not updated yet
        if ($mulligan && in_array('dynasty_mulligan', $event->getTransition()?->getTos() ?? [], true)) {
            foreach ($game->getPlayers() as $player) {
                $this->cardInspector->lookAtPile($player, $player, HiddenPile::Provinces, null);
            }
        }
    }
}
