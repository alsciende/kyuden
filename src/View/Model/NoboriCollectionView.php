<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\BannerPattern;
use App\Enum\Mon;

/**
 * What the user may choose for their nobori (NoboriCollection): the ensō and no pattern from the start, and what they
 * have earned.
 */
final readonly class NoboriCollectionView
{
    /**
     * @param list<Mon>           $mons
     * @param list<BannerPattern> $patterns
     */
    public function __construct(
        public array $mons,
        public array $patterns,
    ) {
    }
}
