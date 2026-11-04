<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A kind of status token placed on a card by an effect: the honored or dishonored status token.
 */
enum StatusToken: string
{
    case Honored = 'honored';
    case Dishonored = 'dishonored';

    /**
     * The status a card with this token alone has.
     */
    public function toPersonalHonor(): PersonalHonor
    {
        return match ($this) {
            self::Honored => PersonalHonor::Honored,
            self::Dishonored => PersonalHonor::Dishonored,
        };
    }
}
