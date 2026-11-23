<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\Element;
use App\Enum\Orientation;
use App\Enum\Phase;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Trigger\PhaseBeginningTrigger;
use App\Trigger\PhaseEndingTrigger;

/**
 * The cards interrupting a phase that would begin or end (doc/interrupts.md, 4).
 */
class PhaseInterruptsTest extends AbstractKernelTestCase
{
    use InterruptOffers;

    private const array ALIASES = ['tsukune', 'guardian', 'warden', 'pass', 'alignment', 'curse', 'trading'];

    public function testEveryCardInterruptingAPhaseSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing([
            '01-shiba-tsukune', '11-jurojin-s-curse', '21-warden-of-the-damned', '30-ethereal-alignment',
            '32-hidden-mountain-pass', '33-pious-guardian',
        ], $this->subscribers(PhaseEndingTrigger::class));
        self::assertSame(['17-trading-on-the-sand-road'], $this->subscribers(PhaseBeginningTrigger::class));
    }

    private function scenarioWith(bool $bowed = false): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_phase_ends')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['tsukune' => '01-shiba-tsukune'])
                ->inPlay(['guardian' => '33-pious-guardian'])
                ->inPlay(['kisada' => '01-hida-kisada', 'curse' => '11-jurojin-s-curse'], bowed: $bowed)
                ->province('01-entrenched-position', as: 'earth', orientation: Orientation::UpsideDown)
                ->province('01-entrenched-position', dynasty: '32-hidden-mountain-pass', dynastyAs: 'pass', dynastyFaceup: true, orientation: Orientation::FaceUp)
                ->hand(['alignment' => '30-ethereal-alignment']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['warden' => '21-warden-of-the-damned'])
                ->hand(['trading' => '17-trading-on-the-sand-road']))
            ->persist();
    }

    public function testTheConflictPhaseEnds(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame([
            'jane:forced' => ['warden'],
            'john:standard' => ['tsukune', 'guardian', 'pass'],
        ], $this->offers($scenario, [new PhaseEndingTrigger(Phase::Conflict)], self::ALIASES));
    }

    public function testEtherealAlignmentWithABrokenProvinceSharingAnElementWithAClaimedRing(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_ends')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', orientation: Orientation::UpsideDown)
                ->hand(['alignment' => '30-ethereal-alignment']))
            ->withPlayer('jane')
            ->ring(Element::Earth, claimedBy: 'john')
            ->persist();

        self::assertSame(['john:standard' => ['alignment']], $this->offers($scenario, [new PhaseEndingTrigger(Phase::Conflict)], ['alignment']));
    }

    public function testPiousGuardianWithTwoBrokenProvinces(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_ends')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['guardian' => '33-pious-guardian'])
                ->province('01-entrenched-position', orientation: Orientation::UpsideDown)
                ->province('01-entrenched-position', orientation: Orientation::UpsideDown))
            ->withPlayer('jane')
            ->persist();

        self::assertSame([], $this->offers($scenario, [new PhaseEndingTrigger(Phase::Conflict)], ['guardian']));
    }

    public function testTheFatePhaseEndsWithTheCursedCharacterReady(): void
    {
        self::assertSame(['john:forced' => ['curse']], $this->offers($this->scenarioWith(), [new PhaseEndingTrigger(Phase::Fate)], self::ALIASES));
        self::assertSame([], $this->offers($this->scenarioWith(bowed: true), [new PhaseEndingTrigger(Phase::Fate)], self::ALIASES));
    }

    public function testTheDrawPhaseWouldBegin(): void
    {
        self::assertSame(['jane:would' => ['trading']], $this->offers($this->scenarioWith(), [new PhaseBeginningTrigger(Phase::Draw)], self::ALIASES));
    }
}
