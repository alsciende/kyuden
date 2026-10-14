<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A stack as the viewing player sees it: a hidden stack (deck, opponent's hand) only shows its number of cards.
 */
final readonly class StackView
{
    /**
     * @param string               $type  snake case name of the StackType
     * @param list<CardView>|null $cards from the bottom to the top; null when the stack is hidden
     */
    public function __construct(
        public string $id,
        public string $type,
        public int $count,
        public ?array $cards,
    ) {
    }
}
