<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\StackType;
use App\Factory\StackFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The out-of-play stacks a card is set aside in, created when a card first goes there (so that the games started before
 * they existed have none): the cards removed from the game of a player (one stack each), the cards under a card (one
 * stack for each host, of the owner of the host, removed when it leaves its place).
 */
readonly class SetAsideStacks
{
    public function __construct(
        private StackFactory $stackFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function removedFromGame(Player $player): Stack
    {
        return $player->getStacks(StackType::RemovedFromGame)->first() ?: $this->stackFactory->createStack($player, StackType::RemovedFromGame);
    }

    public function underneath(PhysicalCard $host): Stack
    {
        return $this->findUnderneath($host) ?? $this->stackFactory->createStack($host->getPlayer(), StackType::Underneath, $host);
    }

    public function findUnderneath(PhysicalCard $host): ?Stack
    {
        foreach ($host->getPlayer()->getStacks(StackType::Underneath) as $stack) {
            if ($stack->getHost() === $host) {
                return $stack;
            }
        }

        return null;
    }

    /**
     * Removes an Underneath stack once its cards have left it.
     */
    public function remove(Stack $stack): void
    {
        if (! $stack->getCards()->isEmpty()) {
            throw new \LogicException(sprintf('Stack %s still holds cards.', $stack->getId()));
        }

        $stack->getPlayer()->getStacks()->removeElement($stack);
        $this->entityManager->remove($stack);
    }
}
