<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\LogicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\StackType;
use PHPUnit\Framework\TestCase;

class StackTest extends TestCase
{
    public function testMoveAround(): void
    {
        $hand = new Stack('hand', self::createStub(Player::class), StackType::InPlay);

        $card1 = new DynastyCard('1', self::createStub(LogicalCard::class), $hand);
        $card2 = new DynastyCard('2', self::createStub(LogicalCard::class), $hand);
        $card3 = new DynastyCard('3', self::createStub(LogicalCard::class), $hand);

        $stack = new Stack('test', self::createStub(Player::class), StackType::InPlay);

        $stack->addCard($card1);
        $stack->addCard($card2);
        $stack->addCard($card3, above: false);

        self::assertEquals(1, $card1->getPosition());
        self::assertEquals(2, $card2->getPosition());
        self::assertEquals(0, $card3->getPosition());

        $stack->moveCard($card3);

        self::assertEquals(0, $card1->getPosition());
        self::assertEquals(1, $card2->getPosition());
        self::assertEquals(2, $card3->getPosition());

        $stack->moveCard($card2, above: false);

        self::assertEquals(1, $card1->getPosition());
        self::assertEquals(0, $card2->getPosition());
        self::assertEquals(2, $card3->getPosition());
    }

    public function testTopCardsFollowPositionsNotCollectionOrder(): void
    {
        $deck = new Stack('deck', self::createStub(Player::class), StackType::DynastyDrawDeck);
        self::assertNull($deck->getTopCard());

        $card1 = new DynastyCard('1', self::createStub(LogicalCard::class), $deck);
        $card2 = new DynastyCard('2', self::createStub(LogicalCard::class), $deck);
        $card3 = new DynastyCard('3', self::createStub(LogicalCard::class), $deck);
        $deck->addCard($card1);
        $deck->addCard($card2);
        $deck->addCard($card3);

        self::assertSame([$card3, $card2], $deck->getTopCards(2));

        // changes the positions but not the order of the collection
        $deck->moveCard($card1);

        self::assertSame([$card1, $card2, $card3], $deck->getCards()->getValues());
        self::assertSame([$card1, $card3], $deck->getTopCards(2));
        self::assertSame($card1, $deck->getTopCard());
        self::assertSame([$card2, $card3, $card1], $deck->getOrderedCards());
        self::assertCount(3, $deck->getTopCards(10));
    }
}
