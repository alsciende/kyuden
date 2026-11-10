<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Service\OpportunityService;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

/**
 * The first opportunity of an action window goes to the player the next action opportunities have been given to
 * (GiveActionOpportunity: Child of the Plains), instead of the first player or the defending player: after the
 * listeners that activate them.
 */
#[AsEnterListener(workflow: 'game', place: 'play_cards_from_provinces', priority: -10)]
#[AsEnterListener(workflow: 'game', place: 'draw_phase_action_window', priority: -10)]
#[AsEnterListener(workflow: 'game', place: 'conflict_phase_action_window', priority: -10)]
#[AsEnterListener(workflow: 'game', place: 'conflict_action_window', priority: -10)]
#[AsEnterListener(workflow: 'game', place: 'fate_phase_action_window', priority: -10)]
readonly class GivenActionOpportunityEnterListener
{
    public function __construct(
        private OpportunityService $opportunityService,
        private GameStateManager $stateManager,
    ) {
    }

    /**
     * @param EnterEvent<Game> $event
     */
    public function __invoke(EnterEvent $event): void
    {
        $game = $event->getSubject();
        $player = $this->opportunityService->takeGivenActionOpportunity($game);
        if ($player === null) {
            return;
        }

        $this->stateManager->withState(
            $game,
            fn (GameState $state): GameState => $state->setActivePlayer($player),
        );
    }
}
