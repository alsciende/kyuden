<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Entity\CardTypes\ProvinceCard;
use App\Enum\ConflictType;
use App\Enum\Element;
use App\Enum\Orientation;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Trigger\ConflictDeclaringTrigger;
use App\Trigger\ConflictLosingTrigger;
use App\Trigger\GloryCountBeginningTrigger;
use App\Trigger\HonorLosingTrigger;

/**
 * The cards interrupting the steps of a conflict and the honor lost (doc/interrupts.md, 5; Duty, 8).
 */
class ConflictInterruptsTest extends AbstractKernelTestCase
{
    use InterruptOffers;

    private const array ALIASES = ['ikebana', 'duty', 'toshimoko', 'kiyono', 'terrain'];

    public function testEveryCardInterruptingTheConflictSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing(['12-ikebana-artisan', '15-duty'], $this->subscribers(HonorLosingTrigger::class));
        self::assertSame(['16-kakita-toshimoko'], $this->subscribers(ConflictLosingTrigger::class));
        self::assertSame(['22-ikoma-kiyono'], $this->subscribers(GloryCountBeginningTrigger::class));
        // Seven Stings Keep is left out: the defenders cannot be declared before the attackers
        self::assertSame(['24-know-the-terrain'], $this->subscribers(ConflictDeclaringTrigger::class));
    }

    private function scenarioWith(int $honor = 10, bool $kiyonoBowed = true): Scenario
    {
        return $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(12)
                ->inPlay(['kisada' => '01-hida-kisada'])
                ->inPlay(['kiyono' => '22-ikoma-kiyono'], bowed: $kiyonoBowed))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor($honor)
                ->inPlay(['ikebana' => '12-ikebana-artisan'])
                ->inPlay(['toshimoko' => '16-kakita-toshimoko'])
                ->province('01-entrenched-position', as: 'attacked')
                ->province('01-entrenched-position', as: 'other')
                ->hand(['duty' => '15-duty', 'terrain' => '24-know-the-terrain']))
            ->conflict('john', 'attacked', attackers: ['kisada'], defenders: ['toshimoko'])
            ->persist();
    }

    public function testTheHonorLostForAnUnopposedConflict(): void
    {
        $scenario = $this->scenarioWith();
        $jane = $scenario->player('jane');

        self::assertSame(['jane:would' => ['ikebana']], $this->offers($scenario, [new HonorLosingTrigger($jane, 1, unopposed: true)], self::ALIASES));
        // honor lost by an effect: not Ikebana Artisan
        self::assertSame([], $this->offers($this->scenarioWith(), [new HonorLosingTrigger($jane, 1)], self::ALIASES));
    }

    public function testDutyWhenThePlayerWouldLoseTheirLastHonor(): void
    {
        $scenario = $this->scenarioWith(honor: 2);

        self::assertSame(['jane:would' => ['duty']], $this->offers($scenario, [new HonorLosingTrigger($scenario->player('jane'), 2)], self::ALIASES));
    }

    public function testKakitaToshimokoWouldLoseTheConflict(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame(['jane:would' => ['toshimoko']], $this->offers($scenario, [new ConflictLosingTrigger($scenario->player('jane'), $scenario->player('john'))], self::ALIASES));
    }

    public function testIkomaKiyonoBowedAndMoreHonorable(): void
    {
        self::assertSame(['john:would' => ['kiyono']], $this->offers($this->scenarioWith(), [new GloryCountBeginningTrigger()], self::ALIASES));
        self::assertSame([], $this->offers($this->scenarioWith(kiyonoBowed: false), [new GloryCountBeginningTrigger()], self::ALIASES));
        self::assertSame([], $this->offers($this->scenarioWith(honor: 12), [new GloryCountBeginningTrigger()], self::ALIASES));
    }

    public function testKnowTheTerrainAgainstAFacedownProvince(): void
    {
        $scenario = $this->scenarioWith();
        $province = $scenario->card('attacked');
        \assert($province instanceof ProvinceCard);
        $trigger = new ConflictDeclaringTrigger($scenario->player('john'), $scenario->player('jane'), $province, Element::Fire, ConflictType::Military);

        self::assertSame(['jane:standard' => ['terrain']], $this->offers($scenario, [$trigger], self::ALIASES));
    }

    public function testKnowTheTerrainWithoutAnotherFacedownProvince(): void
    {
        $scenario = $this->scenario()
            ->atPlace('declare_conflict')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'attacked')
                ->province('01-entrenched-position', orientation: Orientation::FaceUp)
                ->hand(['terrain' => '24-know-the-terrain']))
            ->persist();
        $province = $scenario->card('attacked');
        \assert($province instanceof ProvinceCard);
        $trigger = new ConflictDeclaringTrigger($scenario->player('john'), $scenario->player('jane'), $province, Element::Fire, ConflictType::Military);

        self::assertSame([], $this->offers($scenario, [$trigger], ['terrain']));
    }
}
