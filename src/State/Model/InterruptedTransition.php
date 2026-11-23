<?php

declare(strict_types=1);

namespace App\State\Model;

/**
 * An automatic transition of the game whose interrupt window has closed (InterruptWindowService): applied next by
 * WorkflowTransitionMiddleware without opening its window again, the cards and the rings whose condition was cancelled
 * left out by its listeners; every condition cancelled, its listeners do not do what it would have done (the honor of an
 * unopposed conflict not lost, the first player token kept), or another transition replaces it (the draw phase
 * skipped).
 */
class InterruptedTransition
{
    /**
     * @param list<string> $cancelledCardIds
     * @param list<string> $cancelledRings
     */
    public function __construct(
        public string $name,
        public array $cancelledCardIds = [],
        public array $cancelledRings = [],
        public bool $cancelled = false,
    ) {
    }

    /**
     * Whether the transition named was interrupted and every condition of its window cancelled. Not prefixed with
     * `is`, so that the serializer of the state does not take it for a property.
     */
    public function cancelledFor(string $name): bool
    {
        return $this->name === $name && $this->cancelled;
    }
}
