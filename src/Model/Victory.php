<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Player;
use App\Enum\VictoryCondition;

/**
 * How a game ends: its winner and the condition they met, or no winner (every player has abandoned the game).
 */
final readonly class Victory
{
    public function __construct(
        public ?Player $winner,
        public VictoryCondition $condition,
    ) {
    }
}
