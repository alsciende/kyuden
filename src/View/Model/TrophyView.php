<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\BannerPattern;
use App\Enum\Mon;
use App\Enum\Trophy;

/**
 * A trophy of the catalogue for a user: what it gives for the nobori (a mon or a pattern, else neither), and, once
 * earned, when and in which game (null for a trophy that no game gives, or a game deleted since).
 */
final readonly class TrophyView
{
    public function __construct(
        public Trophy $trophy,
        public ?Mon $rewardMon,
        public ?BannerPattern $rewardPattern,
        public bool $earned,
        public ?\DateTimeImmutable $earnedAt,
        public ?string $gameId,
    ) {
    }
}
