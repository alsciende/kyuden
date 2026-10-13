<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\ConflictType;
use App\Exception\Rules\NotEnoughFateException;

class PlayerState
{
    /**
     * Stronghold format: a player with no honor is eliminated, a player with 25 or more honor wins the game.
     */
    public const int MINIMUM_HONOR = 0;
    public const int MAXIMUM_HONOR = 25;

    /**
     * Stronghold format: one opportunity to declare a military conflict and one to declare a political conflict.
     */
    public const int CONFLICT_OPPORTUNITIES = 2;

    private ?string $strongholdProvinceId = null;
    private ?int $honor = null;
    private int $fate = 0;

    /**
     * Turn records: what the player has done during the current round. Reset at the end of the round.
     */
    private bool $fateCollected = false;

    private ?int $honorBid = null;

    /**
     * Conflict opportunities left this round: declaring a conflict or passing a conflict opportunity uses one.
     */
    private int $conflictOpportunities = self::CONFLICT_OPPORTUNITIES;

    /**
     * Types of the conflicts declared this round: a player declares at most one conflict of each type.
     *
     * @var list<ConflictType>
     */
    private array $declaredConflictTypes = [];

    /**
     * The player has passed during the current step. Reset when a step in which players can pass begins.
     */
    private bool $passed = false;

    public function setStrongholdProvinceId(?string $strongholdProvinceId): void
    {
        $this->strongholdProvinceId = $strongholdProvinceId;
    }

    public function getStrongholdProvinceId(): ?string
    {
        return $this->strongholdProvinceId;
    }

    public function getHonor(): ?int
    {
        return $this->honor;
    }

    /**
     * A player cannot lose honor they do not have: honor stops at the minimum. Reaching the minimum or the maximum
     * ends the game (`end_game` transition).
     */
    public function setHonor(?int $honor): self
    {
        $this->honor = $honor === null ? null : max($honor, self::MINIMUM_HONOR);

        return $this;
    }

    public function incrementHonor(int $gain): self
    {
        return $this->setHonor($this->honor + $gain);
    }

    public function decrementHonor(int $loss): self
    {
        return $this->setHonor($this->honor - $loss);
    }

    public function getFate(): int
    {
        return $this->fate;
    }

    public function setFate(int $fate): self
    {
        $this->fate = $fate;

        return $this;
    }

    public function incrementFate(int $gain): self
    {
        return $this->setFate($this->fate + $gain);
    }

    /**
     * @throws NotEnoughFateException if the fate pool does not hold that much fate
     */
    public function spendFate(int $amount): self
    {
        if ($amount > $this->fate) {
            throw new NotEnoughFateException($amount, $this->fate);
        }

        return $this->setFate($this->fate - $amount);
    }

    public function isFateCollected(): bool
    {
        return $this->fateCollected;
    }

    public function setFateCollected(bool $fateCollected): self
    {
        $this->fateCollected = $fateCollected;

        return $this;
    }

    /**
     * Number selected on the honor dial during the draw phase; null until the player has bid this round.
     */
    public function getHonorBid(): ?int
    {
        return $this->honorBid;
    }

    public function setHonorBid(?int $honorBid): self
    {
        $this->honorBid = $honorBid;

        return $this;
    }

    public function getConflictOpportunities(): int
    {
        return $this->conflictOpportunities;
    }

    public function setConflictOpportunities(int $conflictOpportunities): self
    {
        $this->conflictOpportunities = $conflictOpportunities;

        return $this;
    }

    /**
     * @return list<ConflictType>
     */
    public function getDeclaredConflictTypes(): array
    {
        return $this->declaredConflictTypes;
    }

    /**
     * @param list<ConflictType> $declaredConflictTypes
     */
    public function setDeclaredConflictTypes(array $declaredConflictTypes): self
    {
        $this->declaredConflictTypes = $declaredConflictTypes;

        return $this;
    }

    /**
     * The player has passed during the current step: the opportunity to act skips them.
     */
    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function setPassed(bool $passed): self
    {
        $this->passed = $passed;

        return $this;
    }

    public function resetTurnRecords(): self
    {
        return $this->setFateCollected(false)
            ->setHonorBid(null)
            ->setConflictOpportunities(self::CONFLICT_OPPORTUNITIES)
            ->setDeclaredConflictTypes([]);
    }
}
