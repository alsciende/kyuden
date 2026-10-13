<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Repository\CardRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * 3.2.5: when the attacking player won by at least the strength of an attacked province, that province is broken.
 * Each attacked province is compared separately. Breaking the stronghold province ends the game (VictoryService).
 */
#[AsTransitionListener(workflow: 'game', transition: 'provinces_broken')]
readonly class ProvincesBrokenTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private CardRepository $cardRepository,
        private WorkflowInterface $provinceCardStateMachine,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();
        $conflict = $this->stateManager->getState($game)->currentConflict();
        if (! $conflict->attackerWon()) {
            return;
        }

        $brokenProvinceIds = [];
        foreach ($conflict->attackedProvinceIds as $provinceId) {
            $province = $this->cardRepository->get($provinceId, $game);
            if ($province->getCurrentPlace() === ProvinceCard::STATE_UNBROKEN && $conflict->excessSkill() >= ($conflict->provinceStrengths[$provinceId] ?? 0)) {
                $this->provinceCardStateMachine->apply($province, 'break');
                $brokenProvinceIds[] = $provinceId;
            }
        }

        $this->stateManager->withState($game, function (GameState $state) use ($brokenProvinceIds): GameState {
            $state->currentConflict()->brokenProvinceIds = $brokenProvinceIds;

            return $state;
        });
    }
}
