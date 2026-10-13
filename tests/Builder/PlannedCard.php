<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\Enum\Orientation;
use App\Enum\PersonalHonor;

/**
 * @internal used by PlayerBuilder
 */
final readonly class PlannedCard
{
    public function __construct(
        public string $code,
        public ?string $alias = null,
        public ?Orientation $orientation = null,
        public bool $chosenStrongholdProvince = false,
        public int $fate = 0,
        public PersonalHonor $personalHonor = PersonalHonor::Ordinary,
    ) {
    }
}
