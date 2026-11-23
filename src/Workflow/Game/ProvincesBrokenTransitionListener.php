<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Event\ProvinceBrokenEvent;
use App\Service\ConflictService;
use App\Service\ControlService;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * 3.2.5: when the attacking player won by at least the strength of an attacked province, that province is broken
 * (ConflictService::findProvincesToBreak()). The interrupts to its breaking come first (InterruptOfferer): those an
 * interrupt has cancelled stay unbroken (Public Forum). Breaking the stronghold province ends the game
 * (VictoryService). Once the broken provinces are recorded, the cards reacting to each one may be triggered
 * (ProvinceBrokenEvent).
 */
#[AsTransitionListener(workflow: 'game', transition: 'provinces_broken')]
readonly class ProvincesBrokenTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private ConflictService $conflictService,
        #[Target('province_card')]
        private WorkflowInterface $provinceCardStateMachine,
        private ControlService $controlService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();
        $state = $this->stateManager->getState($game);
        // the provinces an interrupt has kept unbroken (CancelImminentTrigger)
        $kept = $state->interruptedTransition?->name === 'provinces_broken' ? $state->interruptedTransition->cancelledCardIds : [];

        $brokenProvinces = [];
        foreach ($this->conflictService->findProvincesToBreak($game) as $province) {
            if (! in_array($province->getId(), $kept, true)) {
                $this->provinceCardStateMachine->apply($province, 'break');
                $brokenProvinces[] = $province;
            }
        }

        $this->stateManager->withState($game, function (GameState $state) use ($brokenProvinces): GameState {
            $state->currentConflict()->brokenProvinceIds = array_map(fn (ProvinceCard $province): string => $province->getId(), $brokenProvinces);

            return $state;
        });
        $attacker = $game->getPlayer($state->currentConflict()->attackingPlayerId);
        foreach ($brokenProvinces as $province) {
            $this->eventDispatcher->dispatch(new ProvinceBrokenEvent($game, $province, $this->controlService->getController($province), $attacker));
        }
    }
}
