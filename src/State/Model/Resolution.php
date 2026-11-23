<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\AbilityTiming;

/**
 * An event played or an ability triggered that its player resolves and has not ended yet (EndResolution): the card
 * whose text it is (the event, or the card holding the ability, an attachment for the abilities it grants) and the
 * timing it was played or triggered with. It tells the players which effect is being applied; the engine does not
 * apply it. Its effects may have been cancelled before they initiated (`cancelled`).
 */
class Resolution
{
    public function __construct(
        public string $cardId,
        public AbilityTiming $timing,
        /**
         * Its effects have been cancelled in the interrupt window before it (CancelImminentTrigger): the player only
         * ends it.
         */
        public bool $cancelled = false,
    ) {
    }

    /**
     * Not prefixed with `is`, so that the serializer of the state does not take it for a property.
     */
    public function interruptOrReaction(): bool
    {
        return $this->timing !== AbilityTiming::Action;
    }
}
