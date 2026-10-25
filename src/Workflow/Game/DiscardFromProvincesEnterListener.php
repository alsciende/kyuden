<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Service\ProvinceRefiller;
use App\State\Manager\GameStateManager;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * 4.6: in player order, each faceup card in a broken province is discarded, and the province refilled once empty. The
 * players then discard the faceup dynasty cards they choose from their unbroken provinces (DiscardCard, which refills
 * them), then pass (AllPlayersOpportunityEnterListener).
 */
#[AsEnterListener(workflow: 'game', place: 'discard_from_provinces')]
readonly class DiscardFromProvincesEnterListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private WorkflowInterface $dynastyCardStateMachine,
        private ProvinceRefiller $provinceRefiller,
    ) {
    }

    /**
     * @param EnterEvent<Game> $event
     */
    public function __invoke(EnterEvent $event): void
    {
        $game = $event->getSubject();

        foreach ($this->stateManager->getState($game)->playerIdsFromFirstPlayer() as $playerId) {
            $brokenProvinces = $game->getPlayer($playerId)->getCardsByCurrentPlace(ProvinceCard::STATE_BROKEN);
            foreach ($brokenProvinces as $province) {
                $faceupCards = $province->getStack()->getCards()->filter(
                    fn (PhysicalCard $card): bool => $card instanceof DynastyCard && $card->getCurrentPlace() === DynastyCard::STATE_PROVINCE_FACEUP,
                );
                if ($faceupCards->isEmpty()) {
                    continue;
                }

                foreach ($faceupCards as $card) {
                    $this->dynastyCardStateMachine->apply($card, 'discard_card');
                }
                $this->provinceRefiller->refillIfEmpty($province->getStack());
            }
        }
    }
}
