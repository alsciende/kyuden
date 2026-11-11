<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\UserFixtures;
use App\Entity\Deck;
use App\Repository\DeckRepository;
use App\Service\Deck\DeckConstructionValidator;
use App\Tests\AbstractKernelTestCase;

class UserFixturesTest extends AbstractKernelTestCase
{
    public function testTheDecksOfTheUsersAreLegal(): void
    {
        $decks = UserFixtures::userDecks(\dirname(__DIR__, 2));

        self::assertNotEmpty($decks);
        foreach ($decks as $id => $deck) {
            self::assertSame([], self::service(DeckConstructionValidator::class)->validate($deck['cards']), $id);
        }
    }

    public function testTheDecksOfTheUsersAreLoaded(): void
    {
        $deck = self::service(DeckRepository::class)->find('jane-dragon-scorpion');

        self::assertInstanceOf(Deck::class, $deck);
        self::assertSame('jane', $deck->getOwner()?->getId());
        self::assertSame('Secrets of the Mountain', $deck->getName());
    }
}
