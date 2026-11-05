<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\AbilityTiming;

/**
 * An event played or an ability triggered that a player resolves and has not ended yet, with the timing it was played
 * or triggered with. The card follows the rules of Visibility where it is now: its id when the viewer sees its stack,
 * its code when they see its face; neither once it no longer exists (shuffled into a deck).
 */
final readonly class ResolutionView
{
    public function __construct(
        public ?string $cardId,
        public ?string $code,
        public AbilityTiming $timing,
    ) {
    }
}
