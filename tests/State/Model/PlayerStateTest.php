<?php

declare(strict_types=1);

namespace App\Tests\State\Model;

use App\Enum\ConflictType;
use App\Exception\Rules\HonorNotSetException;
use App\Exception\Rules\NoConflictOpportunityException;
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

    public function testAPlayerDeclaresOneConflictOfEachType(): void
    {
        $state = new PlayerState()->useConflictOpportunity(ConflictType::Military);

        self::assertSame([ConflictType::Political], $state->declarableConflictTypes());

        $this->expectException(NoConflictOpportunityException::class);

        $state->useConflictOpportunity(ConflictType::Military);
    }

    public function testAnAdditionalConflictAllowsASecondConflictOfItsType(): void
    {
        $state = new PlayerState()
            ->useConflictOpportunity(ConflictType::Military)
            ->grantConflictOpportunity(ConflictType::Military);

        self::assertSame(2, $state->getConflictOpportunities());
        self::assertSame([ConflictType::Military, ConflictType::Political], $state->declarableConflictTypes());

        $state->useConflictOpportunity(ConflictType::Military)->useConflictOpportunity(ConflictType::Political);

        self::assertSame([], $state->declarableConflictTypes());
    }

    public function testAnAdditionalConflictGrantedBeforeTheFirstOneOfItsType(): void
    {
        $state = new PlayerState()
            ->grantConflictOpportunity(ConflictType::Military)
            ->useConflictOpportunity(ConflictType::Military)
            ->useConflictOpportunity(ConflictType::Military);

        self::assertSame([ConflictType::Political], $state->declarableConflictTypes());

        $this->expectException(NoConflictOpportunityException::class);

        $state->useConflictOpportunity(ConflictType::Military);
    }

    public function testTheAdditionalConflictsEndWithTheRound(): void
    {
        $state = new PlayerState()
            ->grantConflictOpportunity(ConflictType::Political)
            ->resetTurnRecords();

        self::assertSame(PlayerState::CONFLICT_OPPORTUNITIES, $state->getConflictOpportunities());
        self::assertSame([], $state->getAdditionalConflictTypes());
    }
}
