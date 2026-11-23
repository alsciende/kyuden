<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Trigger\CharacterDishonoringTrigger;
use App\Trigger\CharacterHonoringTrigger;
use App\Trigger\FateRemovingTrigger;

/**
 * The cards interrupting a character honored, dishonored or losing fate (doc/interrupts.md, 7).
 */
class CharacterStatusInterruptsTest extends AbstractKernelTestCase
{
    use InterruptOffers;

    private const array ALIASES = ['rumormonger', 'shomon', 'void'];

    public function testEveryCardInterruptingAStatusSubscribesToIt(): void
    {
        self::assertSame(['01-young-rumormonger'], $this->subscribers(CharacterHonoringTrigger::class));
        self::assertEqualsCanonicalizing(['01-young-rumormonger', '15-kitsuki-shomon'], $this->subscribers(CharacterDishonoringTrigger::class));
        self::assertSame(['02-embrace-the-void'], $this->subscribers(FateRemovingTrigger::class));
    }

    private function scenarioWith(): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['shomon' => '15-kitsuki-shomon'])
                ->inPlay(['kisada' => '01-hida-kisada', 'void' => '02-embrace-the-void'], fate: 2))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['rumormonger' => '01-young-rumormonger']))
            ->persist();
    }

    public function testACharacterWouldBeHonored(): void
    {
        $scenario = $this->scenarioWith();

        // john controls another character: the honor may go to it
        self::assertSame(['jane:would' => ['rumormonger']], $this->offers($scenario, [new CharacterHonoringTrigger($scenario->card('kisada'), $scenario->player('john'))], self::ALIASES));
        // jane controls no other character
        self::assertSame([], $this->offers($scenario, [new CharacterHonoringTrigger($scenario->card('rumormonger'), $scenario->player('jane'))], self::ALIASES));
    }

    public function testAnotherCharacterWouldBeDishonored(): void
    {
        $scenario = $this->scenarioWith();
        $john = $scenario->player('john');

        self::assertSame(['jane:would' => ['rumormonger'], 'john:would' => ['shomon']], $this->offers($scenario, [new CharacterDishonoringTrigger($scenario->card('kisada'), $john)], self::ALIASES));
    }

    public function testKitsukiShomonItselfWouldBeDishonored(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame(['jane:would' => ['rumormonger']], $this->offers($scenario, [new CharacterDishonoringTrigger($scenario->card('shomon'), $scenario->player('john'))], self::ALIASES));
    }

    public function testFateWouldBeRemovedFromTheAttachedCharacter(): void
    {
        $scenario = $this->scenarioWith();
        $john = $scenario->player('john');

        self::assertSame(['john:would' => ['void']], $this->offers($scenario, [new FateRemovingTrigger($scenario->card('kisada'), $john, 1)], self::ALIASES));
        self::assertSame([], $this->offers($scenario, [new FateRemovingTrigger($scenario->card('shomon'), $john, 1)], self::ALIASES));
    }
}
