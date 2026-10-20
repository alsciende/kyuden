<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\ConflictType;

/**
 * A player as the viewing player sees them. Secret information (pending honor bid, chosen stronghold province) is
 * null for the opponents.
 */
final readonly class PlayerView
{
    /**
     * @param list<ConflictType> $declaredConflictTypes
     * @param list<StackView>    $stacks
     */
    public function __construct(
        public string $id,
        public string $userId,
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
         * Number of interrupts and reactions the player has initiated and has not ended yet.
         */
        public int $resolvingInterruptsAndReactions,
        public bool $fateCollected,
        public int $conflictOpportunities,
        public array $declaredConflictTypes,
        public ?int $gloryCount,
        public array $stacks,
    ) {
    }
}
