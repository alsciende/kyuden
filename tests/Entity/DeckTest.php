<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Deck;
use App\Entity\User;
use App\Enum\UserType;
use PHPUnit\Framework\TestCase;

class DeckTest extends TestCase
{
    public function testTheContentHashFollowsTheCards(): void
    {
        $deck = new Deck('deck');
        $deck->setCards(['01-banzai' => 3, '01-fine-katana' => 2]);

        self::assertSame(hash('sha256', "01-banzai:3\n01-fine-katana:2"), $deck->getContentHash());
    }

    public function testTheContentHashIgnoresTheOrderOfTheCards(): void
    {
        self::assertSame(
            Deck::hashCards(['01-banzai' => 3, '01-fine-katana' => 2]),
            Deck::hashCards(['01-fine-katana' => 2, '01-banzai' => 3]),
        );
        self::assertNotSame(
            Deck::hashCards(['01-banzai' => 3, '01-fine-katana' => 2]),
            Deck::hashCards(['01-banzai' => 2, '01-fine-katana' => 2]),
        );
    }

    public function testADeletedDeckCanBeUsedByNobody(): void
    {
        $john = new User('john', 'John', UserType::Human);
        $deck = new Deck('deck');
        $deck->setOwner($john);
        $deck->delete(new \DateTimeImmutable());

        self::assertFalse($deck->isUsableBy($john));

        $deck->restore();

        self::assertTrue($deck->isUsableBy($john));
    }
}
