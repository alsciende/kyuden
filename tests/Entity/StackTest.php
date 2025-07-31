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
        $stack = new Stack('test', self::createMock(Player::class), StackType::InPlay);

        $card1 = new DynastyCard('1', self::createMock(LogicalCard::class));
        $card2 = new DynastyCard('2', self::createMock(LogicalCard::class));
        $card3 = new DynastyCard('3', self::createMock(LogicalCard::class));

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
}
