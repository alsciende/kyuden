<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\PhysicalCard;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

/**
 * Marking store of the card workflows: the place of a card is computed from its stack and its orientation.
 *
 * Transitions must move or turn the card (see CardTransitionListener); setMarking() only checks that
 * the card ended up in the place the transition leads to.
 */
readonly class CardMarkingStore implements MarkingStoreInterface
{
    #[\Override]
    public function getMarking(object $subject): Marking
    {
        if (! $subject instanceof PhysicalCard) {
            throw new \InvalidArgumentException(sprintf('%s only supports %s.', self::class, PhysicalCard::class));
        }

        return new Marking([
            $subject->getCurrentPlace() => 1,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        if (! $subject instanceof PhysicalCard) {
            throw new \InvalidArgumentException(sprintf('%s only supports %s.', self::class, PhysicalCard::class));
        }

        $expected = array_keys($marking->getPlaces());
        $actual = $subject->getCurrentPlace();
        if ($expected !== [$actual]) {
            throw new \LogicException(sprintf('Card %s should be in place %s but its stack and orientation put it in place %s.', $subject->getId(), implode(', ', $expected), $actual));
        }
    }
}
