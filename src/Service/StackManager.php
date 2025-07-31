<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\StackType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

readonly class StackManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function new(Player $player, StackType $type): Stack
    {
        $stack = new Stack(Uuid::v4()->toString(), $player, $type);
        $this->entityManager->persist($stack);

        return $stack;
    }
}
