<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Repository\CardRepository;
use App\State\Manager\GameStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * 3.2: the players react to the conflict declaration once the defending player has revealed the attacked province
 * (RevealProvince).
 */
#[AsGuardListener(workflow: 'game', transition: 'attacked_province_revealed')]
readonly class AttackedProvinceRevealedGuardListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(GuardEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        foreach ($this->stateManager->getState($game)->currentConflict()->attackedProvinceIds as $provinceId) {
            if ($this->cardRepository->get($provinceId, $game)->getCurrentPlace() === ProvinceCard::STATE_FACEDOWN) {
                $event->setBlocked(true, 'The defending player must reveal the attacked province.');

                return;
            }
        }
    }
}
