<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Repository\CardRepository;
use App\Service\ConflictService;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

/**
 * Each attacked province has its printed strength, plus the strength bonus of the stronghold for the stronghold
 * province, until the defending player declares another one (DeclareProvinceStrength).
 */
#[AsEnterListener(workflow: 'game', place: 'determine_result')]
readonly class DetermineResultEnterListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private CardRepository $cardRepository,
        private ConflictService $conflictService,
    ) {
    }

    public function __invoke(EnterEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->stateManager->withState($game, function (GameState $state) use ($game): GameState {
            $conflict = $state->currentConflict();
            foreach ($conflict->attackedProvinceIds as $provinceId) {
                $province = $this->cardRepository->get($provinceId, $game);
                $strength = (int) $province->getLogicalCard()->getStrength();
                if ($province instanceof ProvinceCard && $this->conflictService->isStrongholdProvince($province)) {
                    $strength += (int) $province->getPlayer()->getStronghold()->getLogicalCard()->getStrengthBonus();
                }
                $conflict->provinceStrengths[$provinceId] = $strength;
            }

            return $state;
        });
    }
}
