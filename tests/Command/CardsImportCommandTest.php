<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\DataFixtures\AppFixtures;
use App\Entity\Cycle;
use App\Entity\Deck;
use App\Entity\ExternalCard;
use App\Entity\LogicalCard;
use App\Entity\Pack;
use App\Entity\Printing;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CardsImportCommandTest extends AbstractKernelTestCase
{
    private function import(): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:cards:import'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }

    /**
     * @param class-string $class
     */
    private function recordsOf(string $class): int
    {
        $count = self::service(EntityManagerInterface::class)->createQueryBuilder()
            ->select('COUNT(e)')->from($class, 'e')->getQuery()->getSingleScalarResult();
        \assert(\is_int($count));

        return $count;
    }

    public function testImportingAgainCreatesNothing(): void
    {
        $counts = array_map($this->recordsOf(...), [Cycle::class, Pack::class, LogicalCard::class, Printing::class, ExternalCard::class, Deck::class]);

        $display = $this->import()->getDisplay();

        self::assertStringContainsString('Created: 0 cycles, 0 packs, 0 cards, 0 printings', $display);
        self::assertSame($counts, array_map($this->recordsOf(...), [Cycle::class, Pack::class, LogicalCard::class, Printing::class, ExternalCard::class, Deck::class]));
    }

    public function testAPackBelongsToItsCycleAtItsPosition(): void
    {
        $pack = self::service(EntityManagerInterface::class)->find(Pack::class, 'a-champion-s-foresight');
        self::assertInstanceOf(Pack::class, $pack);

        self::assertSame(5, $pack->getPosition());
        self::assertSame('inheritance', $pack->getCycle()->getId());
        self::assertSame(6, $pack->getCycle()->getPosition());
    }

    public function testWhatChangedIsWrittenAgainAndWhatIsMissingIsCreated(): void
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $card = $entityManager->find(LogicalCard::class, '01-above-question');
        self::assertInstanceOf(LogicalCard::class, $card);
        $card->setName('Below Question');
        $printing = $card->getPrintings()->first();
        self::assertInstanceOf(Printing::class, $printing);
        $card->getPrintings()->removeElement($printing);
        $entityManager->remove($printing);
        $starterDeck = $entityManager->find(Deck::class, AppFixtures::CRAB_STARTER_DECK);
        self::assertInstanceOf(Deck::class, $starterDeck);
        $starterDeck->setCards([]);
        $entityManager->flush();
        $entityManager->clear();

        $display = $this->import()->getDisplay();

        self::assertStringContainsString('Created: 0 cycles, 0 packs, 0 cards, 1 printings', $display);
        $entityManager->clear();
        $card = $entityManager->find(LogicalCard::class, '01-above-question');
        self::assertInstanceOf(LogicalCard::class, $card);
        self::assertSame('Above Question', $card->getName());
        self::assertCount(1, $card->getPrintings());
        $starterDeck = $entityManager->find(Deck::class, AppFixtures::CRAB_STARTER_DECK);
        self::assertInstanceOf(Deck::class, $starterDeck);
        self::assertSame(50, array_sum($starterDeck->getCards()));
        self::assertNull($starterDeck->getOwner());
    }
}
