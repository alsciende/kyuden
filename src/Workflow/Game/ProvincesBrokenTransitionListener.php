<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Event\ProvinceBrokenEvent;
use App\Repository\CardRepository;
use App\State\Manager\GameStateManager;
use App\Service\ControlService;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * 3.2.5: when the attacking player won by at least the strength of an attacked province, that province is broken.
 * Each attacked province is compared separately. Breaking the stronghold province ends the game (VictoryService). Once
 * the broken provinces are recorded, the cards reacting to each one may be triggered (ProvinceBrokenEvent).
 */
#[AsTransitionListener(workflow: 'game', transition: 'provinces_broken')]
readonly class ProvincesBrokenTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private CardRepository $cardRepository,
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
        $conflict = $this->stateManager->getState($game)->currentConflict();
        if (! $conflict->attackerWon()) {
            return;
        }

        $brokenProvinces = [];
        foreach ($conflict->attackedProvinceIds as $provinceId) {
            $province = $this->cardRepository->get($provinceId, $game);
            if ($province instanceof ProvinceCard && $province->getCurrentPlace() === ProvinceCard::STATE_UNBROKEN && $conflict->excessSkill() >= ($conflict->provinceStrengths[$provinceId] ?? 0)) {
                $this->provinceCardStateMachine->apply($province, 'break');
                $brokenProvinces[] = $province;
            }
        }

        $this->stateManager->withState($game, function (GameState $state) use ($brokenProvinces): GameState {
            $state->currentConflict()->brokenProvinceIds = array_map(fn (ProvinceCard $province): string => $province->getId(), $brokenProvinces);

            return $state;
        });
        $attacker = $game->getPlayer($conflict->attackingPlayerId);
        foreach ($brokenProvinces as $province) {
            $this->eventDispatcher->dispatch(new ProvinceBrokenEvent($game, $province, $this->controlService->getController($province), $attacker));
        }
    }
}
