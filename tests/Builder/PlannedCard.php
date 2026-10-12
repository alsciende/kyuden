<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\Enum\Orientation;

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
    ) {
    }
}
