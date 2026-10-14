<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Enum\ConflictType;
use App\Exception\Rules\NoConflictOpportunityException;
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
     * Honor bid: the number shown on the honor dial once revealed. It stays as a reference until the next bid.
     */
    private ?int $honorBid = null;

    /**
     * Number secretly selected on the honor dial, until the dials are revealed.
     */
    private ?int $pendingHonorBid = null;

    /**
     * Turn records: what the player has done during the current round. Reset at the end of the round.
     */
    private bool $fateCollected = false;

    /**
     * Conflict opportunities left this round: declaring a conflict or passing a conflict opportunity uses one.
     */
    private int $conflictOpportunities = self::CONFLICT_OPPORTUNITIES;

    /**
     * Glory count declared by the player during the conflict phase, claimed rings excluded.
     */
    private ?int $gloryCount = null;

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
     * Revealed honor bid, which card effects can change; null from the start of a bid until the dials are revealed.
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

    /**
     * Secret honor bid, known only to the player; null until the player has bid, and once the dials are revealed.
     */
    public function getPendingHonorBid(): ?int
    {
        return $this->pendingHonorBid;
    }

    public function setPendingHonorBid(?int $pendingHonorBid): self
    {
        $this->pendingHonorBid = $pendingHonorBid;

        return $this;
    }

    /**
     * A new honor bid begins: the previous one is no longer a reference while the dial is being turned.
     */
    public function startHonorBid(): self
    {
        return $this->setHonorBid(null)->setPendingHonorBid(null);
    }

    /**
     * The honor dials are revealed: the secret bid becomes the honor bid.
     */
    public function revealHonorBid(): self
    {
        return $this->setHonorBid($this->pendingHonorBid)->setPendingHonorBid(null);
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
     * Uses a conflict opportunity: to declare a conflict of that type, or to pass (no type).
     *
     * @throws NoConflictOpportunityException if no opportunity is left, or a conflict of that type was declared
     */
    public function useConflictOpportunity(?ConflictType $type): self
    {
        if ($this->conflictOpportunities <= 0 || in_array($type, $this->declaredConflictTypes, true)) {
            throw new NoConflictOpportunityException($type);
        }

        $this->conflictOpportunities--;
        if ($type instanceof ConflictType) {
            $this->declaredConflictTypes[] = $type;
        }

        return $this;
    }

    public function getGloryCount(): ?int
    {
        return $this->gloryCount;
    }

    public function setGloryCount(?int $gloryCount): self
    {
        $this->gloryCount = $gloryCount;

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
            ->setConflictOpportunities(self::CONFLICT_OPPORTUNITIES)
            ->setDeclaredConflictTypes([])
            ->setGloryCount(null);
    }
}
