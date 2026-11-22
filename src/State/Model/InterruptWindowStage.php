<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\InterruptStage;

/**
 * A stage of an interrupt window: the players asked (public: decided on what every player sees, InterruptOfferer),
 * the cards each of them can interrupt with (secret: the cards may be in their hand), and those asked about a card out
 * of sight of the opponent, whose client passes for them after a while, whether they hold the card or not.
 */
class InterruptWindowStage
{
    /**
     * @param list<string>                $askedPlayerIds
     * @param array<string, list<string>> $cardIds        by player
     * @param array<string, list<string>> $forcedCardIds  by player: those of the cards whose interrupt is forced
     * @param list<string>                $timedPlayerIds
     */
    public function __construct(
        public InterruptStage $stage,
        public array $askedPlayerIds = [],
        public array $cardIds = [],
        public array $forcedCardIds = [],
        public array $timedPlayerIds = [],
    ) {
    }
}
