<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\DataFixtures\AppFixtures;
use App\Entity\Game;
use App\Entity\Player;
use App\Tests\AbstractKernelTestCase;
use App\Workflow\Game\StartGameTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

class StartGameTransitionListenerTestCase extends AbstractKernelTestCase
{
    public function testInitPlayer(): void
    {
        /** @var StartGameTransitionListener $service */
        $service = self::getContainer()->get(StartGameTransitionListener::class);

        $game = $this->createGame();
        self::assertInstanceOf(Game::class, $game);

        $player = $this->createPlayer($game, AppFixtures::LION_STARTER_DECK, 'john');
        self::assertInstanceOf(Player::class, $player);

        self::assertEquals(0, $player->getStacks()->count());

        $event = new TransitionEvent($game, self::createMock(Marking::class));
        $service($event);

        self::assertEquals(11, $player->getStacks()->count());
    }
}
