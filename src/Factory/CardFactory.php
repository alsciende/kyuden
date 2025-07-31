<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\LogicalCard;
use App\Entity\PhysicalCard;
use App\Entity\Stack;
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

    public function createCard(string $physicalCardId, string $logicalCardId, Stack $stack): PhysicalCard
    {
        $logicalCard = $this->cardRepository->find($logicalCardId);
        if (! $logicalCard instanceof LogicalCard) {
            throw new LogicalCardNotFoundException($logicalCardId);
        }

        $classname = $this->getClassname($logicalCard->getSide()->value);

        /** @var PhysicalCard $card */
        $card = new $classname($physicalCardId, $logicalCard, $stack);
        $card->setTitle($logicalCard->getName());

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
}
