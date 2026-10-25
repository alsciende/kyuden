<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Enum\StackType;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsGuardListener(workflow: 'game', transition: 'dynasty_cards_revealed')]
class DynastyCardsRevealedGuardListener
{
    public function __construct()
    {
    }

    /**
     * @param GuardEvent<Game> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        $game = $event->getSubject();

        foreach ($game->getPlayers() as $player) {
            foreach ($player->getStacks(StackType::RegularProvince) as $provinceStack) {
                foreach ($provinceStack->getCards() as $card) {
                    if ($card->getCurrentPlace() === DynastyCard::STATE_PROVINCE_FACEDOWN) {
                        $event->setBlocked(true, 'You must reveal all facedown dynasty cards before proceeding.');

                        return;
                    }
                }
            }
        }
    }
}
