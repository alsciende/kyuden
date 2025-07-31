<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\DataFixtures\AppFixtures;
use App\Entity\Game;
use App\Entity\Player;
use App\Repository\DeckRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Tests\CreateGameTrait;
use App\Tests\CreatePlayerTrait;
use App\Workflow\Game\StartGameTransitionListener;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

class StartGameTransitionListenerTest extends KernelTestCase
{
    use CreateGameTrait;
    use CreatePlayerTrait;

    private MessageBusInterface $messageBus;
    private GameRepository $gameRepository;
    private PlayerRepository $playerRepository;
    private DeckRepository $deckRepository;

    #[Before]
    protected function getServices(): void
    {
        $container = static::getContainer();
        // @phpstan-ignore assign.propertyType
        $this->messageBus = $container->get(MessageBusInterface::class);
        // @phpstan-ignore assign.propertyType
        $this->gameRepository = $container->get(GameRepository::class);
        // @phpstan-ignore assign.propertyType
        $this->playerRepository = $container->get(PlayerRepository::class);
        // @phpstan-ignore assign.propertyType
        $this->deckRepository = $container->get(DeckRepository::class);
    }

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
