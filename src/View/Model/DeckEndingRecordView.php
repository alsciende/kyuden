<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\VictoryCondition;

/**
 * The games of a deck won and lost by a victory condition.
 */
final readonly class DeckEndingRecordView
{
    public function __construct(
        public VictoryCondition $victoryCondition,
        public int $won,
        public int $lost,
    ) {
    }
}
