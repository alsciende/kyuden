<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What passing an opportunity to act means in a step with opportunities (`opportunities` metadata of the game place).
 */
enum PassingRule: string
{
    /**
     * Passing forfeits the remaining opportunities of the step, which ends once every player has passed.
     */
    case Final = 'final';

    /**
     * Action windows: a player who acts cancels the passes, so a player who passed gets the opportunity to act again.
     * The step ends once every player has passed consecutively.
     */
    case Consecutive = 'consecutive';
}
