<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Game;
use App\Message\CreateGame;
use App\Repository\GameRepository;
use App\Service\EventPlayer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class EventPlayerTest extends KernelTestCase
{
    public function testPlayEvent(): void
    {
        $kernel = self::bootKernel();
        $container = static::getContainer();

        $gameId = Uuid::v4()->toString();
        /** @var EventPlayer $player */
        $player = $container->get(EventPlayer::class);

        $player->play(CreateGame::class, sprintf('{"game_id":"%s","seed":"%s"}', $gameId, bin2hex(random_bytes(32))));

        /** @var GameRepository $repository */
        $repository = $container->get(GameRepository::class);

        $this->assertInstanceOf(Game::class, $repository->get($gameId));
    }
}
