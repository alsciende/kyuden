<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Entity\Stack;
use App\Enum\StackType;
use PHPUnit\Framework\TestCase;

class StackTest extends TestCase
{
    public function testAddAbove(): void
    {
        $stack = new Stack('test', self::createMock(Player::class), StackType::InPlay);

        $card1 = self::createMock(PhysicalCard::class);
        $card2 = self::createMock(PhysicalCard::class);
        $card3 = self::createMock(PhysicalCard::class);

        $stack->addCard($card1);
        $stack->addCard($card2);
        $stack->addCard($card3);

        self::assertEquals([$card1, $card2, $card3], $stack->getCards()->toArray());
    }

    public function testAddBelow(): void
    {
        $stack = new Stack('test', self::createMock(Player::class), StackType::InPlay);

        $card1 = self::createMock(PhysicalCard::class);
        $card2 = self::createMock(PhysicalCard::class);
        $card3 = self::createMock(PhysicalCard::class);

        $stack->addCard($card1, above: false);
        $stack->addCard($card2, above: false);
        $stack->addCard($card3, above: false);

        self::assertEquals([$card3, $card2, $card1], $stack->getCards()->toArray());
    }
}
