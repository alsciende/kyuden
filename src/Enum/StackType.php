<?php

declare(strict_types=1);

namespace App\Enum;

enum StackType: int
{
    case ConflictDrawDeck = 0;
    case ConflictDiscardPile = 1;
    case DynastyDrawDeck = 2;
    case DynastyDiscardPile = 3;
    case Role = 4;
    case Hand = 5;
    case Province = 6;
    case InPlay = 7;

    public function isUnique(): bool
    {
        return $this->value < 6;
    }
}
