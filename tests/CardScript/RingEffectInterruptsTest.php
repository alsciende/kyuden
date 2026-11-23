<?php

declare(strict_types=1);

namespace App\Tests\CardScript;

use App\Enum\Element;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Trigger\RingEffectResolvingTrigger;

/**
 * The cards interrupting the ring effect the attacking player would resolve (doc/interrupts.md, 6).
 */
class RingEffectInterruptsTest extends AbstractKernelTestCase
{
    use InterruptOffers;

    private const array ALIASES = ['azunami', 'nothing', 'heirloom', 'leniency', 'talisman'];

    public function testEveryCardInterruptingARingEffectSubscribesToIt(): void
    {
        self::assertEqualsCanonicalizing([
            '12-asako-azunami', '13-all-and-nothing', '14-restored-heirloom', '16-leniency', '42-jade-talisman',
        ], $this->subscribers(RingEffectResolvingTrigger::class));
    }

    private function scenarioWith(bool $cheapCharacter = true): Scenario
    {
        return $this->scenario()
            ->atPlace('react_to_broken_province')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['azunami' => '12-asako-azunami'])
                ->province('01-entrenched-position', dynasty: $cheapCharacter ? '01-doji-whisperer' : '01-hida-kisada', dynastyFaceup: true)
                ->conflictDiscardPile(['heirloom' => '14-restored-heirloom'])
                ->hand(['nothing' => '13-all-and-nothing', 'leniency' => '16-leniency']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['hotaru' => '01-doji-hotaru', 'talisman' => '42-jade-talisman']))
            ->persist();
    }

    public function testTheWaterRing(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame([
            'jane:would' => ['talisman'],
            'john:would' => ['azunami', 'heirloom', 'leniency'],
        ], $this->offers($scenario, [new RingEffectResolvingTrigger(Element::Water, $scenario->player('john'))], self::ALIASES));
    }

    public function testTheVoidRing(): void
    {
        $scenario = $this->scenarioWith();

        self::assertSame([
            'jane:would' => ['talisman'],
            'john:would' => ['nothing', 'leniency'],
        ], $this->offers($scenario, [new RingEffectResolvingTrigger(Element::Void, $scenario->player('john'))], self::ALIASES));
    }

    public function testLeniencyWithoutACharacterOfCost2OrLowerInTheProvinces(): void
    {
        $scenario = $this->scenarioWith(cheapCharacter: false);

        self::assertSame([
            'jane:would' => ['talisman'],
            'john:would' => ['nothing'],
        ], $this->offers($scenario, [new RingEffectResolvingTrigger(Element::Void, $scenario->player('john'))], self::ALIASES));
    }
}
