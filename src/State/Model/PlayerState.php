<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Exception\Rules\MaximumHonorReachedException;
use App\Exception\Rules\MinimumHonorReachedException;
use App\Exception\Rules\NotEnoughFateException;

class PlayerState
{
    public const int MINIMUM_HONOR = 0;
    public const int MAXIMUM_HONOR = 25;

    private ?string $strongholdProvinceId = null;
    private ?int $honor = null;
    private int $fate = 0;

    /**
     * Turn records: what the player has done during the current round. Reset at the end of the round.
     */
    private bool $fateCollected = false;

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

    public function setHonor(?int $honor): self
    {
        $this->honor = $honor;

        if (is_int($this->honor) && $this->honor <= self::MINIMUM_HONOR) {
            throw new MinimumHonorReachedException();
        }

        if (is_int($this->honor) && $this->honor >= self::MAXIMUM_HONOR) {
            throw new MaximumHonorReachedException();
        }

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
        return $this->setFateCollected(false);
    }
}
