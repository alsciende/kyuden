<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PhysicalCard;
use App\Entity\Stack;
use App\Event\DeckShuffledEvent;
use App\Factory\CardFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Shuffling a stack makes the players lose track of its cards: each card is deleted and created again (same logical
 * card, player, stack and orientation) with a new id, so that a card whose id has been seen cannot be tracked in the
 * shuffled stack. The new cards have a fresh state.
 *
 * The shuffle starts from the ordered cards, so that the result only depends on the state of the random generator of
 * the game. Code holding a card of the stack must read it again from the stack after the shuffle.
 */
readonly class StackShuffler
{
    public function __construct(
        private CardFactory $cardFactory,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function shuffle(Stack $stack): void
    {
        $game = $stack->getPlayer()->getGame();
        /** @var list<PhysicalCard> $shuffledCards */
        $shuffledCards = $game->getRandomizer()->shuffleArray($stack->getOrderedCards());

        foreach ($shuffledCards as $card) {
            $stack->removeCard($card);
            $card->getPlayer()->getPhysicalCards()->removeElement($card);
            $game->getPhysicalCards()->removeElement($card);
            $this->entityManager->remove($card);
        }

        // from the bottom to the top of the new stack
        foreach ($shuffledCards as $card) {
            $this->cardFactory->createCard($game->generateId(), $card->getLogicalCard(), $stack, $card->getOrientation());
        }

        $this->eventDispatcher->dispatch(new DeckShuffledEvent($game, $stack));
    }
}
