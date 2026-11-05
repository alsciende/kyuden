<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\AbilityTiming;

/**
 * An event played or an ability triggered that its player resolves and has not ended yet (EndResolution): the card
 * whose text it is (the event, or the card holding the ability, an attachment for the abilities it grants) and the
 * timing it was played or triggered with. It tells the players which effect is being applied; the engine does not
 * apply it.
 */
class Resolution
{
    public function __construct(
        public string $cardId,
        public AbilityTiming $timing,
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
