<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Message\DrawConflictCards;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GameLogCommandTest extends AbstractKernelTestCase
{
    public function testShowsTheLogAsAPlayerReadsIt(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->conflictDeck(['01-banzai']))
            ->withPlayer('jane')
            ->persist();
        $john = $scenario->player('john')->getId();
        $this->messageBus->dispatch(new DrawConflictCards($scenario->game()->getId(), $john, $john, 1));
        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:game:log'));

        $tester->execute(['id' => $scenario->game()->getId(), '--as' => 'john']);
        self::assertSame("#1 draw_conflict_cards by john\n    john draws Banzai!\n", $tester->getDisplay());

        $tester->execute(['id' => $scenario->game()->getId()]);
        self::assertSame("#1 draw_conflict_cards by john\n    john draws a hidden card\n", $tester->getDisplay());
    }
}
