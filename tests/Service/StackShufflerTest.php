<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\PhysicalCard;
use App\Entity\Stack;
use App\Enum\Orientation;
use App\Enum\StackType;
use App\Repository\CardRepository;
use App\Service\StackShuffler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Doctrine\ORM\EntityManagerInterface;

class StackShufflerTest extends AbstractKernelTestCase
{
    private const array DECK = [
        'bottom' => '01-akodo-toturi',
        'middle' => '01-doji-hotaru',
        'top' => '01-imperial-storehouse',
    ];

    public function testShufflingCreatesTheCardsAgainWithNewIds(): void
    {
        $scenario = $this->scenarioWithDynastyDeck();
        $john = $scenario->player('john');
        $deck = $john->getUniqueStack(StackType::DynastyDrawDeck);
        $oldCards = array_map($scenario->card(...), array_keys(self::DECK));

        self::service(StackShuffler::class)->shuffle($deck);

        $newCards = $deck->getOrderedCards();
        self::assertSame([0, 1, 2], array_map(fn (PhysicalCard $card): ?int => $card->getPosition(), $newCards));
        self::assertEqualsCanonicalizing(array_values(self::DECK), $this->codes($newCards));
        self::assertSame([], array_intersect($this->ids($oldCards), $this->ids($newCards)));
        foreach ($newCards as $card) {
            self::assertSame($john, $card->getPlayer());
            self::assertSame(Orientation::FaceDown, $card->getOrientation());
            self::assertContains($card, $john->getPhysicalCards());
            self::assertContains($card, $scenario->game()->getPhysicalCards());
        }
        foreach ($oldCards as $card) {
            self::assertNotContains($card, $john->getPhysicalCards());
            self::assertNotContains($card, $scenario->game()->getPhysicalCards());
        }
    }

    public function testTheShuffleOnlyDependsOnTheRandomGeneratorAndThePositions(): void
    {
        $results = [];
        foreach ([false, true] as $reversedCollection) {
            $deck = $this->scenarioWithDynastyDeck(str_repeat('seed', 8))->player('john')->getUniqueStack(StackType::DynastyDrawDeck);
            if ($reversedCollection) {
                // same positions, collection in the reverse order
                $cards = $deck->getCards()->getValues();
                $deck->getCards()->clear();
                foreach (array_reverse($cards) as $card) {
                    $deck->getCards()->add($card);
                }
            }

            self::service(StackShuffler::class)->shuffle($deck);

            $results[] = [$this->codes($deck->getOrderedCards()), $this->ids($deck->getOrderedCards())];
        }

        self::assertSame($results[0], $results[1]);
    }

    public function testTheShuffledStackIsSaved(): void
    {
        $scenario = $this->scenarioWithDynastyDeck(persist: true);
        $deck = $scenario->player('john')->getUniqueStack(StackType::DynastyDrawDeck);
        $oldId = $scenario->card('top')->getId();

        self::service(StackShuffler::class)->shuffle($deck);
        $newIds = $this->ids($deck->getOrderedCards());
        $entityManager = self::service(EntityManagerInterface::class);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $entityManager->find(Stack::class, $deck->getId());
        self::assertInstanceOf(Stack::class, $reloaded);
        self::assertSame($newIds, $this->ids($reloaded->getOrderedCards()));
        self::assertNull(self::service(CardRepository::class)->find($oldId));
    }

    private function scenarioWithDynastyDeck(?string $seed = null, bool $persist = false): Scenario
    {
        $builder = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->dynastyDeck(self::DECK))
            ->withPlayer('jane');
        if ($seed !== null) {
            $builder->seed($seed);
        }

        return $persist ? $builder->persist() : $builder->build();
    }

    /**
     * @param list<PhysicalCard> $cards
     *
     * @return list<string>
     */
    private function codes(array $cards): array
    {
        return array_map(fn (PhysicalCard $card): string => $card->getLogicalCard()->getId(), $cards);
    }

    /**
     * @param list<PhysicalCard> $cards
     *
     * @return list<string>
     */
    private function ids(array $cards): array
    {
        return array_map(fn (PhysicalCard $card): string => $card->getId(), $cards);
    }
}
