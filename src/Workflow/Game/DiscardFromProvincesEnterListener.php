<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\State\Manager\GameStateManager;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * 4.6: in player order, each faceup card in a broken province is discarded. The players then discard the faceup
 * dynasty cards they choose from their unbroken provinces and refill the empty provinces (DiscardCard, FillProvince),
 * then pass (AllPlayersOpportunityEnterListener).
 */
#[AsEnterListener(workflow: 'game', place: 'discard_from_provinces')]
readonly class DiscardFromProvincesEnterListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private WorkflowInterface $dynastyCardStateMachine,
    ) {
    }

    public function __invoke(EnterEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        foreach ($this->stateManager->getState($game)->playerIdsFromFirstPlayer() as $playerId) {
            $brokenProvinces = $game->getPlayer($playerId)->getCardsByCurrentPlace(ProvinceCard::STATE_BROKEN);
            foreach ($brokenProvinces as $province) {
                $faceupCards = $province->getStack()->getCards()->filter(
                    fn (PhysicalCard $card): bool => $card instanceof DynastyCard && $card->getCurrentPlace() === DynastyCard::STATE_PROVINCE_FACEUP,
                );
                foreach ($faceupCards as $card) {
                    $this->dynastyCardStateMachine->apply($card, 'discard_card');
                }
            }
        }
    }
}
