<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Player;
use App\Enum\VictoryCondition;

final readonly class Victory
{
    public function __construct(
        public Player $winner,
        public VictoryCondition $condition,
    ) {
    }
}
