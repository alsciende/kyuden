<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\LogicalCard;
use App\Entity\PhysicalCard;
use App\Entity\Stack;
use App\Enum\Orientation;
use App\Exception\Data\LogicalCardNotFoundException;
use App\Repository\LogicalCardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;

readonly class CardFactory
{
    public function __construct(
        private LogicalCardRepository $cardRepository,
        private Registry $registry,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Creates a card on top of the stack, owned by the player of the stack, with the given orientation
     * (by default the initial orientation of its type).
     */
    public function createCard(string $physicalCardId, LogicalCard $logicalCard, Stack $stack, ?Orientation $orientation = null): PhysicalCard
    {
        $classname = $this->getClassname($logicalCard->getSide()->value);

        /** @var PhysicalCard $card */
        $card = new $classname($physicalCardId, $logicalCard, $stack, $orientation);
        $card->setTitle($logicalCard->getName());
        $stack->addCard($card);
        $stack->getPlayer()->addCard($card);
        $stack->getPlayer()->getGame()->addCard($card);

        // fails if the card cannot be in that stack with that orientation
        $this->registry->get($card)->getMarking($card);
        $this->entityManager->persist($card);

        return $card;
    }

    private function getClassname(string $side): string
    {
        $classname = PhysicalCard::DISCRIMINATOR_MAP[$side] ?? null;

        if ($classname === null) {
            throw new \LogicException("Unknown card side {$side}");
        }

        return $classname;
    }

    public function getLogicalCard(string $logicalCardId): LogicalCard
    {
        $logicalCard = $this->cardRepository->find($logicalCardId);
        if (! $logicalCard instanceof LogicalCard) {
            throw new LogicalCardNotFoundException($logicalCardId);
        }

        return $logicalCard;
    }
}
