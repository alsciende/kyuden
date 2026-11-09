<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Repository\GameRepository;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Service\Replay\PlaysEndedGame;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class GameReplayCommandTest extends AbstractKernelTestCase
{
    use PlaysEndedGame;

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:game:replay'));
    }

    public function testShowsTheLogOfTheReplayAsThePlayerReadsIt(): void
    {
        $game = $this->playEndedGame();
        $tester = $this->tester();

        $tester->execute(['id' => $game->getId(), '--as' => 'john']);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        self::assertStringContainsString('#4 choose_stronghold_province by you', $display);
        self::assertStringContainsString('concede_game by jane', $display);
        self::assertStringContainsString("Replayed {$game->getVersion()} events: identical to the game.", $display);
    }

    public function testNothingOfTheReplayStays(): void
    {
        $game = $this->playEndedGame();
        $games = count(self::service(GameRepository::class)->findAll());

        $this->tester()->execute(['id' => $game->getId(), '--as' => 'john']);

        self::service(EntityManagerInterface::class)->clear();
        self::assertCount($games, self::service(GameRepository::class)->findAll());
    }

    public function testWaitsForThePlayerBeforeEachOfTheirActions(): void
    {
        $game = $this->playEndedGame();
        $tester = $this->tester();
        // before john chooses his stronghold province and before each of his 4 dynasty cards
        $tester->setInputs(array_fill(0, 5, ''));

        $tester->execute(['id' => $game->getId(), '--as' => 'john', '--step' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertSame(5, substr_count($tester->getDisplay(), 'Press Enter to proceed'));
        self::assertStringContainsString('-- next, you: #4 choose_stronghold_province', $tester->getDisplay());
    }

    public function testFailsWhenTheReplayDiverges(): void
    {
        $game = $this->playEndedGame();
        // the game as if it had been played by another engine: its last event is lost
        $game->incrementVersion();
        self::service(EntityManagerInterface::class)->flush();

        $tester = $this->tester();
        $tester->execute(['id' => $game->getId(), '--as' => 'john']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString("The replay differs from the game from version {$game->getVersion()}.", $tester->getDisplay());
    }
}
