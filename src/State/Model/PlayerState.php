<?php

declare(strict_types=1);

namespace App\State\Model;

use App\Exception\Rules\MaximumHonorReachedException;
use App\Exception\Rules\MinimumHonorReachedException;

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

    public function isFateCollected(): bool
    {
        return $this->fateCollected;
    }

    public function setFateCollected(bool $fateCollected): self
    {
        $this->fateCollected = $fateCollected;

        return $this;
    }

    public function resetTurnRecords(): self
    {
        return $this->setFateCollected(false);
    }
}
