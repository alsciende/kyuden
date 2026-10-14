<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Event;
use App\Entity\Game;
use App\Message\CreateGame;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class EventRecordingTest extends KernelTestCase
{
    public function testListener(): void
    {
        $kernel = self::bootKernel();
        $container = static::getContainer();

        $gameId = Uuid::v4()->toString();

        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);

        $seed = bin2hex(random_bytes(32));
        $bus->dispatch(new CreateGame($gameId, $seed));

        /** @var EntityManagerInterface $manager */
        $manager = $container->get(EntityManagerInterface::class);

        /** @var Game $game */
        $game = $manager->getRepository(Game::class)->find($gameId);
        $this->assertSame($gameId, $game->getId());

        $event = $manager->getRepository(Event::class)->findOneBy([], [
            'id' => 'desc',
        ]);
        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame(CreateGame::class, $event->getName());
        $this->assertSame(
            sprintf('{"game_id":"%s","seed":"%s"}', $gameId, $seed),
            $event->getPayload()
        );
    }
}
