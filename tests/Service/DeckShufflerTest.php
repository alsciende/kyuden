<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\CardTypes\DynastyCard;
use App\Entity\LogicalCard;
use App\Entity\Stack;
use App\Service\DeckShuffler;
use PHPUnit\Framework\TestCase;

class DeckShufflerTest extends TestCase
{
    public function testShuffle(): void
    {
        $service = new DeckShuffler();

        $cards = [
            new DynastyCard('1', $this->createStub(LogicalCard::class), self::createMock(Stack::class)),
            new DynastyCard('2', $this->createStub(LogicalCard::class), self::createMock(Stack::class)),
        ];
        $this->assertNull($cards[0]->getPosition());
        $this->assertNull($cards[1]->getPosition());

        $shuffled = $service->shuffleCards($cards);
        $this->assertCount(2, $shuffled);
        $this->assertSame(0, $shuffled[0]->getPosition());
        $this->assertSame(1, $shuffled[1]->getPosition());
    }
}
