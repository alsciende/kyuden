<?php

declare(strict_types=1);

namespace App\Tests\State\Model;

use App\Exception\Rules\NotEnoughFateException;
use App\State\Model\PlayerState;
use PHPUnit\Framework\TestCase;

class PlayerStateTest extends TestCase
{
    public function testSpendingFateRemovesItFromTheFatePool(): void
    {
        $state = new PlayerState()->setFate(5)->spendFate(5);

        self::assertSame(0, $state->getFate());
    }

    public function testCannotSpendMoreFateThanTheFatePoolHolds(): void
    {
        $state = new PlayerState()->setFate(2);

        $this->expectException(NotEnoughFateException::class);

        $state->spendFate(3);
    }
}
