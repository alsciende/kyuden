<?php

declare(strict_types=1);

namespace App\Enum;

enum StackType: int
{
    case ConflictDrawDeck = 0;
    case ConflictDiscardPile = 1;
    case DynastyDrawDeck = 2;
    case DynastyDiscardPile = 3;
    case StrongholdProvince = 4;
    case Role = 5;
    case Hand = 6;
    case RegularProvince = 7;
    case InPlay = 8;

    public function isUnique(): bool
    {
        return $this->value < 7;
    }
}
