<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\Clan;
use App\Enum\ConflictType;
use App\Model\Banner;

/**
 * A player as the viewing player sees them. Secret information (pending honor bid, chosen stronghold province) is
 * null for the opponents.
 */
final readonly class PlayerView
{
    /**
     * @param list<ConflictType> $declaredConflictTypes
     * @param list<ConflictType> $declarableConflictTypes
     * @param list<StackView>    $stacks
     * @param list<ResolutionView> $resolutions
     */
    public function __construct(
        public string $id,
        public string $userId,
        /**
         * The name of the user playing.
         */
        public string $name,
        /**
         * The clan of the stronghold of the player (public, face up from the start), null before their cards exist.
         */
        public ?Clan $clan,
        /**
         * The nobori of the user playing: a mon on a pattern (the house bot: the mon of its clan on a plain nobori).
         */
        public Banner $banner,
        public ?int $honor,
        public int $fate,
        public ?int $honorBid,
        public ?int $pendingHonorBid,
        /**
         * The player has secretly selected an honor bid, not revealed yet: public, unlike the bid itself.
         */
        public bool $honorBidSelected,
        public ?string $strongholdProvinceId,
        public bool $passed,
        /**
         * The player has taken an action during their opportunity to act and has not ended it yet.
         */
        public bool $acted,
        /**
         * The next action opportunities given in an action window that go to the player (GiveActionOpportunity).
         */
        public int $givenActions,
        /**
         * Number of interrupts and reactions the player has initiated and has not ended yet.
         */
        public int $resolvingInterruptsAndReactions,
        /**
         * The events played and the abilities triggered the player resolves, in the order they were initiated: the
         * last one is the one they end first.
         */
        public array $resolutions,
        public bool $fateCollected,
        public int $conflictOpportunities,
        public array $declaredConflictTypes,
        /**
         * The types of the conflicts the player may still declare this round, the additional conflicts included.
         */
        public array $declarableConflictTypes,
        public ?int $gloryCount,
        public array $stacks,
    ) {
    }
}
