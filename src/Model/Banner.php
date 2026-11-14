<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\BannerPattern;
use App\Enum\Mon;

/**
 * The nobori of a player, the vertical banner the other players see: its pattern and the mon at its top. A player
 * starts with the ensō on a nobori without pattern, and chooses among what they have earned (NoboriCollection).
 */
final readonly class Banner
{
    public function __construct(
        public Mon $mon = Mon::Enso,
        public BannerPattern $pattern = BannerPattern::None,
    ) {
    }

    public function withMon(Mon $mon): self
    {
        return new self($mon, $this->pattern);
    }

    public function withPattern(BannerPattern $pattern): self
    {
        return new self($this->mon, $pattern);
    }
}
