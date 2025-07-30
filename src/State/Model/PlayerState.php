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
    private int $honor;

    public function setStrongholdProvinceId(?string $strongholdProvinceId): void
    {
        $this->strongholdProvinceId = $strongholdProvinceId;
    }

    public function getStrongholdProvinceId(): ?string
    {
        return $this->strongholdProvinceId;
    }

    public function getHonor(): int
    {
        return $this->honor;
    }

    public function setHonor(int $honor): self
    {
        $this->honor = $honor;

        if ($this->honor <= self::MINIMUM_HONOR) {
            throw new MinimumHonorReachedException();
        }

        if ($this->honor >= self::MAXIMUM_HONOR) {
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
}
