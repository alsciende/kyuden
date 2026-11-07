<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\StackType;
use Doctrine\ORM\EntityManagerInterface;

readonly class StackFactory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function createStack(Player $player, StackType $type, ?PhysicalCard $host = null): Stack
    {
        $stack = new Stack($player->getGame()->generateId(), $player, $type, $host);
        $this->entityManager->persist($stack);

        return $stack;
    }
}
