<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PhysicalCard;
use App\Entity\Stack;
use App\Exception\Data\MissingStackException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class StackManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createEmptyStack(): Stack
    {
        $stack = new Stack(Uuid::v4()->toString());
        $this->entityManager->persist($stack);

        return $stack;
    }

    public function moveCardOnTop(PhysicalCard $card): void
    {
        if ($card->getStack() === null) {
            throw new MissingStackException($card);
        }

        $card->getStack()->removeCard($card)->addCard($card, above: true);
    }

    public function moveCardOnBottom(PhysicalCard $card): void
    {
        if ($card->getStack() === null) {
            throw new MissingStackException($card);
        }

        $card->getStack()->removeCard($card)->addCard($card, above: false);
    }
}
