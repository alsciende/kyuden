<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\DuelType;

/**
 * The duel being resolved (D. Duel Timing), from its initiation until it ends. Cards are referenced by id.
 * Steps: the players involved bid secretly, then reveal their honor dials and declare their dueling statistic; the
 * result is then known until a player ends the duel.
 */
class DuelState
{
    /**
     * Characters involved on the side of the challenger and on the side of the challenged player.
     *
     * @var list<string>
     */
    public array $challengerCardIds = [];

    /**
     * @var list<string>
     */
    public array $challengedCardIds = [];

    /**
     * The honor dials of the duel have been revealed (D.4).
     */
    public bool $revealed = false;

    /**
     * Dueling statistic of each side, declared by its player (D.6).
     */
    public ?int $challengerStatistic = null;
    public ?int $challengedStatistic = null;

    /**
     * Set when the result is determined (D.7); the winner and the loser are null on a tie.
     */
    public bool $resolved = false;
    public ?string $winnerId = null;
    public ?string $loserId = null;

    public function __construct(
        /**
         * The player whose card ability initiates the duel.
         */
        public string $challengerId,
        public string $challengedId,
        public DuelType $type,
    ) {
    }

    /**
     * Not prefixed with `get`, `is` or `has`, so that the serializer of the state does not take it for a property;
     * same for the other methods.
     *
     * @return list<string>
     */
    public function playerIds(): array
    {
        return [$this->challengerId, $this->challengedId];
    }

    public function involves(string $playerId): bool
    {
        return in_array($playerId, $this->playerIds(), true);
    }

    /**
     * D.3: the players involved select their honor bid.
     */
    public function atBidStep(): bool
    {
        return ! $this->revealed;
    }

    /**
     * D.4 to D.6: the honor dials are revealed, the players declare their dueling statistic.
     */
    public function atStatisticStep(): bool
    {
        return $this->revealed && ! $this->resolved;
    }

    public function statisticsDeclared(): bool
    {
        return $this->challengerStatistic !== null && $this->challengedStatistic !== null;
    }

    /**
     * D.7: each duel total is the dueling statistic plus the honor bid; the higher total wins, nobody on a tie.
     */
    public function determineResult(int $challengerBid, int $challengedBid): void
    {
        $challengerTotal = ($this->challengerStatistic ?? throw new \LogicException('The challenger has not declared their statistic.')) + $challengerBid;
        $challengedTotal = ($this->challengedStatistic ?? throw new \LogicException('The challenged player has not declared their statistic.')) + $challengedBid;
        [$this->winnerId, $this->loserId] = match (true) {
            $challengerTotal > $challengedTotal => [$this->challengerId, $this->challengedId],
            $challengedTotal > $challengerTotal => [$this->challengedId, $this->challengerId],
            default => [null, null],
        };
        $this->resolved = true;
    }
}
