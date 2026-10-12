<?php

declare(strict_types=1);

namespace App\Tests\Builder;

/**
 * @internal used by PlayerBuilder
 */
final readonly class PlannedCard
{
    public function __construct(
        public string $code,
        public ?string $alias = null,
        public bool $faceup = false,
        public bool $chosenStrongholdProvince = false,
    ) {
    }
}
