<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Exception\Data\DataException;
use App\Message\ActionInterface;
use App\Message\BreakProvince;
use App\Repository\CardRepository;
use App\Service\ConflictService;
use App\Service\ControlService;
use App\State\Manager\GameStateManager;
use App\Trigger\ProvinceBreaking;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * A province would be broken (ProvinceBreaking): by an effect (BreakProvince), or by the result of the conflict
 * (`provinces_broken`), each attacked province it breaks.
 */
final readonly class ProvinceBreakTriggerResolver implements ImminentTriggerResolverInterface
{
    public function __construct(
        private CardRepository $cardRepository,
        private ConflictService $conflictService,
        private ControlService $controlService,
        private GameStateManager $gameStateManager,
        #[Target('province_card')]
        private WorkflowInterface $provinceCardStateMachine,
    ) {
    }

    #[\Override]
    public function resolveMessage(ActionInterface $message, Game $game): array
    {
        if (! $message instanceof BreakProvince) {
            return [];
        }

        try {
            $breaker = $game->getPlayer($message->getPlayerId());
            $province = $this->cardRepository->get($message->getProvinceId(), $game);
        } catch (DataException) {
            // refused by its handler: nothing would occur
            return [];
        }

        if (! $province instanceof ProvinceCard || ! $this->provinceCardStateMachine->can($province, 'break')) {
            return [];
        }

        return [new ProvinceBreaking($province, $this->controlService->getController($province), $breaker)];
    }

    #[\Override]
    public function resolveTransition(string $transition, Game $game): array
    {
        if ($transition !== 'provinces_broken') {
            return [];
        }

        $attacker = $game->getPlayer($this->gameStateManager->getState($game)->currentConflict()->attackingPlayerId);

        return array_map(
            fn (ProvinceCard $province): ProvinceBreaking => new ProvinceBreaking($province, $this->controlService->getController($province), $attacker),
            $this->conflictService->findProvincesToBreak($game),
        );
    }
}
