<?php

declare(strict_types=1);

namespace App\Tests\State\Model;

use App\Enum\PersonalHonor;
use App\Exception\Rules\NotEnoughFateException;
use App\Exception\Rules\PersonalHonorException;
use App\State\Model\CardState;
use PHPUnit\Framework\TestCase;

class CardStateTest extends TestCase
{
    public function testHonoringMovesTowardsHonored(): void
    {
        $state = new CardState()->setPersonalHonor(PersonalHonor::Dishonored);

        self::assertSame(PersonalHonor::Ordinary, $state->honor()->getPersonalHonor());
        self::assertSame(PersonalHonor::Honored, $state->honor()->getPersonalHonor());
    }

    public function testDishonoringMovesTowardsDishonored(): void
    {
        $state = new CardState()->setPersonalHonor(PersonalHonor::Honored);

        self::assertSame(PersonalHonor::Ordinary, $state->dishonor()->getPersonalHonor());
        self::assertSame(PersonalHonor::Dishonored, $state->dishonor()->getPersonalHonor());
    }

    public function testAnHonoredCharacterCannotBecomeHonored(): void
    {
        $this->expectException(PersonalHonorException::class);

        new CardState()->setPersonalHonor(PersonalHonor::Honored)->honor();
    }

    public function testADishonoredCharacterCannotBecomeDishonored(): void
    {
        $this->expectException(PersonalHonorException::class);

        new CardState()->setPersonalHonor(PersonalHonor::Dishonored)->dishonor();
    }

    public function testCannotRemoveMoreFateThanTheCardHolds(): void
    {
        $state = new CardState()->setFate(2);

        self::assertSame(1, $state->removeFate(1)->getFate());

        $this->expectException(NotEnoughFateException::class);

        $state->removeFate(2);
    }
}
