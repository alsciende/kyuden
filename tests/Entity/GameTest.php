<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Data\WorkflowException;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

class GameTest extends AbstractKernelTestCase
{
    private const string SEED = '0123456789abcdef0123456789abcdef';

    public function testTwoGamesWithTheSameSeedDrawTheSameValues(): void
    {
        $game1 = new Game('game1', self::SEED);
        $game2 = new Game('game2', self::SEED);

        self::assertSame($game1->getRandomizer()->getInt(0, 1000), $game2->getRandomizer()->getInt(0, 1000));
        self::assertSame($game1->generateId(), $game2->generateId());
    }

    public function testAssertsTheGameIsInOneOfThePlaces(): void
    {
        $game = new Game('game', self::SEED);
        $game->setCurrentPlace('honor_bid');

        $game->assertPlace('collect_fate', 'honor_bid');

        $this->expectException(WorkflowException::class);
        $game->assertPlace('collect_fate');
    }

    public function testGeneratedIdsAreUuidsV4(): void
    {
        $id = new Game('game', self::SEED)->generateId();

        self::assertInstanceOf(UuidV4::class, Uuid::fromString($id));
    }

    public function testTheRandomGeneratorGoesOnAfterTheGameIsReloaded(): void
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $game = new Game('game', self::SEED);
        $entityManager->persist($game);
        $game->getRandomizer()->getInt(0, 1000);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $this->gameRepository->get('game');

        $expected = new Randomizer(new Xoshiro256StarStar(self::SEED));
        $expected->getInt(0, 1000);
        self::assertSame($expected->getInt(0, 1000), $reloaded->getRandomizer()->getInt(0, 1000));
    }

    public function testThePlayersAreInOrderOfArrival(): void
    {
        $scenario = $this->scenario()
            ->atPlace('join_game')
            ->withPlayer('jane')
            ->withPlayer('john')
            ->withPlayer('bob')
            ->persist();
        self::service(EntityManagerInterface::class)->clear();

        $players = $this->gameRepository->get($scenario->game()->getId())->getPlayers()->getValues();

        self::assertSame(['jane', 'john', 'bob'], array_map(fn (Player $player): string => $player->getUserId(), $players));
        self::assertSame([0, 1, 2], array_map(fn (Player $player): int => $player->getJoinRank(), $players));
    }
}
