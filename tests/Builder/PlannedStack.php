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

    /**
     * @param string|null $host alias of the card the cards of an Underneath stack are under
     */
    public function __construct(
        public readonly StackType $type,
        public readonly ?string $host = null,
    ) {
    }
}
