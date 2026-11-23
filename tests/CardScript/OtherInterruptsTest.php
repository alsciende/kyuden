<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\Element;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Trigger\CardsDrawingTrigger;
use App\Trigger\DuelResolvingTrigger;
use App\Trigger\FirstPlayerTokenPassingTrigger;
use App\Trigger\RingReturningTrigger;

/**
 * The cards interrupting the first player token passed, a ring returned, a duel resolved and cards drawn
 * (doc/interrupts.md, 8).
 */
class OtherInterruptsTest extends AbstractKernelTestCase
{
    use InterruptOffers;

    private const array ALIASES = ['unicorn', 'wholeness', 'stay', 'subterfuge'];

    public function testEveryCardInterruptingTheseConditionsSubscribesToThem(): void
    {
        self::assertSame(['01-way-of-the-unicorn'], $this->subscribers(FirstPlayerTokenPassingTrigger::class));
        self::assertSame(['08-wholeness-of-the-world'], $this->subscribers(RingReturningTrigger::class));
        self::assertSame(['16-stay-your-hand'], $this->subscribers(DuelResolvingTrigger::class));
        self::assertSame(['41-subterfuge'], $this->subscribers(CardsDrawingTrigger::class));
    }

    private function scenarioWith(int $janeHonor = 8): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->hand(['unicorn' => '01-way-of-the-unicorn', 'wholeness' => '08-wholeness-of-the-world']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor($janeHonor)
                ->inPlay(['hotaru' => '01-doji-hotaru'])
                ->hand(['stay' => '16-stay-your-hand', 'subterfuge' => '41-subterfuge']))
            ->persist();
    }

    public function testTheFirstPlayerWouldPassTheToken(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame(['john:would' => ['unicorn']], $this->offers($scenario, [new FirstPlayerTokenPassingTrigger($scenario->player('john'), $scenario->player('jane'))], self::ALIASES));
    }

    public function testAClaimedRingWouldBeReturned(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame(['john:would' => ['wholeness']], $this->offers($scenario, [new RingReturningTrigger(Element::Fire, $scenario->player('john'))], self::ALIASES));
        self::assertSame([], $this->offers($scenario, [new RingReturningTrigger(Element::Fire, $scenario->player('jane'))], self::ALIASES));
    }

    public function testADuelOfACharacterOfThePlayerWouldResolve(): void
    {
        $scenario = $this->scenarioWith();
        $trigger = new DuelResolvingTrigger($scenario->player('john'), $scenario->player('jane'), [$scenario->card('kisada')->getId(), $scenario->card('hotaru')->getId()]);

        self::assertSame(['jane:would' => ['stay']], $this->offers($scenario, [$trigger], self::ALIASES));
    }

    public function testTheMoreHonorableOpponentWouldDrawCards(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame(['jane:would' => ['subterfuge']], $this->offers($scenario, [new CardsDrawingTrigger($scenario->player('john'), 1)], self::ALIASES));
        $asHonorable = $this->scenarioWith(janeHonor: 10);
        self::assertSame([], $this->offers($asHonorable, [new CardsDrawingTrigger($asHonorable->player('john'), 1)], self::ALIASES));
    }
}
