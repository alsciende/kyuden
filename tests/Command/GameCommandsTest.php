<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\PhysicalCard;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GameCommandsTest extends AbstractKernelTestCase
{
    public function testListsTheGamesInProgress(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();
        $tester = $this->tester('app:game:list');

        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString($scenario->game()->getId(), $display);
        self::assertStringContainsString('draw_phase_action_window', $display);
        self::assertStringContainsString('[john]', $display);
        self::assertStringContainsString('[jane]', $display);
    }

    public function testListsTheCardsOfAGameInAPlaceForAPlayer(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->hand(['banzai' => '01-banzai'])->conflictDeck(['01-fine-katana']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->hand(['01-ornate-fan']))
            ->persist();
        $tester = $this->tester('app:game:cards');

        $tester->execute(['id' => $scenario->game()->getId(), '--place' => ['hand'], '--player' => 'john']);

        $display = $tester->getDisplay();
        self::assertStringContainsString($scenario->card('banzai')->getId(), $display);
        self::assertStringNotContainsString('01-fine-katana', $display);
        self::assertStringNotContainsString('01-ornate-fan', $display);
        self::assertStringContainsString('1 card(s)', $display);
    }

    public function testSwapsTheLogicalCardOfACard(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->hand(['banzai' => '01-banzai']))
            ->withPlayer('jane')
            ->persist();
        $id = $scenario->card('banzai')->getId();

        $this->tester('app:card:swap')->execute(['card' => $id, 'code' => '01-fine-katana']);

        $card = $this->reload($id);
        self::assertInstanceOf(ConflictCard::class, $card);
        self::assertSame('01-fine-katana', $card->getLogicalCard()->getId());
    }

    public function testSwapsTheSideOfACardWhereTheNewSideCanBe(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->hand(['banzai' => '01-banzai'])
                ->inPlay(['courtier' => '01-otomo-courtier'], fate: 2))
            ->withPlayer('jane')
            ->persist();
        $courtier = $scenario->card('courtier')->getId();
        $banzai = $scenario->card('banzai')->getId();
        $tester = $this->tester('app:card:swap');

        $tester->execute(['card' => $courtier, 'code' => '01-adept-of-shadows']);

        $card = $this->reload($courtier);
        self::assertInstanceOf(ConflictCard::class, $card);
        self::assertSame('01-adept-of-shadows', $card->getLogicalCard()->getId());
        self::assertSame('in_play', $card->getCurrentPlace());
        self::assertStringContainsString('"fate":2', $card->getState());

        $this->expectExceptionMessage('A dynasty card cannot be face_up in stack Hand.');
        $tester->execute(['card' => $banzai, 'code' => '01-otomo-courtier']);
    }

    private function reload(string $id): PhysicalCard
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $entityManager->clear();
        $card = $entityManager->find(PhysicalCard::class, $id);
        self::assertInstanceOf(PhysicalCard::class, $card);

        return $card;
    }

    private function tester(string $name): CommandTester
    {
        return new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find($name));
    }
}
