<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;
use App\Enum\Type;
use App\Factory\CardFactory;
use Symfony\Component\Uid\Uuid;

readonly class GameSetupService
{
    public function __construct(
        private StackManager $stackManager,
        private CardFactory $factory,
    ) {
    }

    public function initPlayer(Player $player): void
    {
        $hand = $this->stackManager->new($player, StackType::Hand);

        foreach ($player->getDeck()->getContent() as $id) {
            $card = $this->factory->createCard(Uuid::v4()->toString(), $id);
            $player->addCard($card);
            $player->getGame()->addCard($card);
            // all cards start in hand and will be moved to the correct stacks later
            $card->setStack($hand);
        }

        $this->stackManager->new($player, StackType::ConflictDrawDeck)
            ->addPlayerCards($player, fn (PhysicalCard $card): bool => $card->getCurrentPlace() === ConflictCard::STATE_DRAW_DECK);

        $this->stackManager->new($player, StackType::ConflictDiscardPile);

        $this->stackManager->new($player, StackType::DynastyDrawDeck)
            ->addPlayerCards($player, fn (PhysicalCard $card): bool => $card->getCurrentPlace() === DynastyCard::STATE_DRAW_DECK);

        $this->stackManager->new($player, StackType::DynastyDiscardPile);

        $this->stackManager->new($player, StackType::Role);

        // 5 provinces
        $provinceCards = $player->getPhysicalCards()->filter(
            fn (PhysicalCard $card): bool => $card->getLogicalCard()->getType() === Type::Province
        );
        foreach ($provinceCards as $provinceCard) {
            $this->stackManager->new($player, StackType::Province)
                ->addCard($provinceCard);
        }
    }
}
