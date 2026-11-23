<?php

declare(strict_types=1);

namespace App\State\Model;

/**
 * An automatic transition of the game whose interrupt window has closed (InterruptWindowService): applied next by
 * WorkflowTransitionMiddleware without opening its window again, the cards whose condition was cancelled left out by
 * its listeners.
 */
class InterruptedTransition
{
    /**
     * @param list<string> $cancelledCardIds
     */
    public function __construct(
        public string $name,
        public array $cancelledCardIds = [],
    ) {
    }
}
