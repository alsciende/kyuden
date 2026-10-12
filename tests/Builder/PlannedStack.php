<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\Enum\StackType;

/**
 * @internal used by PlayerBuilder
 */
final class PlannedStack
{
    /**
     * @var list<PlannedCard>
     */
    public array $cards = [];

    public function __construct(
        public readonly StackType $type,
    ) {
    }
}
