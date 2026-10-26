<?php

declare(strict_types=1);

namespace App\Tests\State\Model;

use App\Exception\Rules\HonorNotSetException;
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

    public function testHonorStopsAtTheMinimum(): void
    {
        $state = new PlayerState()->setHonor(3)->decrementHonor(5);

        self::assertSame(PlayerState::MINIMUM_HONOR, $state->getHonor());
    }

    public function testCannotLoseHonorBeforeItIsSet(): void
    {
        $this->expectException(HonorNotSetException::class);

        new PlayerState()->decrementHonor(1);
    }

    public function testCannotGainHonorBeforeItIsSet(): void
    {
        $this->expectException(HonorNotSetException::class);

        new PlayerState()->incrementHonor(1);
    }

    public function testHonorCanExceedTheMaximum(): void
    {
        $state = new PlayerState()->setHonor(24)->incrementHonor(3);

        self::assertSame(27, $state->getHonor());
    }
}
