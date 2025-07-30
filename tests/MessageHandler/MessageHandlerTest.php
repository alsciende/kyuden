<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\DataFixtures\StarterCraneDeckTrait;
use App\DataFixtures\StarterDeckLionTrait;
use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Type;
use App\Message\ChooseStrongholdProvince;
use App\Message\CreateGame;
use App\Message\JoinGame;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\State\Manager\PlayerStateManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class MessageHandlerTest extends KernelTestCase
{
    use StarterDeckLionTrait;
    use StarterCraneDeckTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testCreateGameHandler(): string
    {
        $container = static::getContainer();

        $gameId = Uuid::v4()->toString();

        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);

        $message = new CreateGame($gameId);
        $bus->dispatch($message);

        /** @var EntityManagerInterface $manager */
        $manager = $container->get(EntityManagerInterface::class);

        /** @var Game $game */
        $game = $manager->getRepository(Game::class)->find($gameId);

        $this->assertInstanceOf(Game::class, $game);
        $this->assertSame('join_game', $game->getCurrentPlace());

        return $gameId;
    }

    #[Depends('testCreateGameHandler')]
    public function testJoinGameHandler(string $gameId): string
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $manager */
        $manager = $container->get(EntityManagerInterface::class);

        /** @var GameRepository $gameRepository */
        $gameRepository = $container->get(GameRepository::class);
        $game = $gameRepository->get($gameId);

        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);

        $playerId = Uuid::v4()->toString();
        $message = new JoinGame(
            $game->getId(),
            $playerId,
            'john',
            'John',
            '1',
            null,
            JoinGame::generateCardsIds($this->getLionStarterCards()),
        );
        $bus->dispatch($message);

        $john = $manager->getRepository(Player::class)->find($playerId);
        $this->assertInstanceOf(Player::class, $john);
        $this->assertSame($game->getId(), $john->getGame()->getId());
        $this->assertSame(1, $game->getPlayers()->count());

        $playerId = Uuid::v4()->toString();
        $message = new JoinGame(
            $game->getId(),
            $playerId,
            'jane',
            'Jane',
            '1',
            null,
            JoinGame::generateCardsIds($this->getCraneStarterCards()),
        );

        $jane = $bus->dispatch($message);

        $jane = $manager->getRepository(Player::class)->find($playerId);
        $this->assertInstanceOf(Player::class, $jane);
        $this->assertSame($game->getId(), $jane->getGame()->getId());
        $this->assertSame(2, $game->getPlayers()->count());
        $this->assertSame(50, $john->getPhysicalCards()->count());
        $this->assertSame(50, $jane->getPhysicalCards()->count());

        $this->assertSame('place_provinces', $game->getCurrentPlace());

        return $gameId;
    }

    #[Depends('testJoinGameHandler')]
    public function testChooseStrongholdProvinceHandler(string $gameId): string
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $manager */
        $manager = $container->get(EntityManagerInterface::class);

        /** @var GameRepository $gameRepository */
        $gameRepository = $container->get(GameRepository::class);
        $game = $gameRepository->get($gameId);
        $this->assertSame(2, $game->getPlayers()->count());
        $john = $game->getPlayers()->get(0);
        $this->assertInstanceOf(Player::class, $john);
        $jane = $game->getPlayers()->get(1);
        $this->assertInstanceOf(Player::class, $jane);

        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);

        /** @var CardRepository $cardRepository */
        $cardRepository = $container->get(CardRepository::class);

        /** @var PlayerStateManager $playerStateManager */
        $playerStateManager = $container->get(PlayerStateManager::class);

        $province = $cardRepository->findOneBy([
            'game' => $game,
            'player' => $john,
            'title' => 'Ancestral Lands',
        ]);
        $this->assertInstanceOf(PhysicalCard::class, $province);

        $message = new ChooseStrongholdProvince(
            $gameId,
            $john->getId(),
            $province->getId()
        );

        $bus->dispatch($message);

        $this->assertSame('place_provinces', $game->getCurrentPlace());

        $province = $cardRepository->findOneBy([
            'game' => $game,
            'player' => $jane,
            'title' => 'Entrenched Position',
        ]);
        $this->assertInstanceOf(PhysicalCard::class, $province);

        $message = new ChooseStrongholdProvince(
            $gameId,
            $jane->getId(),
            $province->getId()
        );

        $bus->dispatch($message);

        $this->assertSame('reveal_facedown_dynasty_cards', $game->getCurrentPlace());

        foreach ($game->getPlayers() as $player) {
            // deck shuffled
            $dynastyPositions = array_map(
                fn (PhysicalCard $card): ?int => $card->getPosition(),
                $player->getCardsByPlace(DynastyCard::STATE_DRAW_DECK)
            );
            $this->assertSame(count($dynastyPositions), count(array_unique($dynastyPositions)));
            // conflict cards drawn
            $this->assertSame(19, count($player->getCardsByPlace(ConflictCard::STATE_DRAW_DECK)));
            $this->assertSame(5, count($player->getCardsByPlace(ConflictCard::STATE_HAND)));
            // starting honor
            $this->assertGreaterThan(0, $playerStateManager->getState($player)->getHonor());
            // filled provinces
            $this->assertSame(4, count($player->getCardsByPlace(DynastyCard::STATE_PROVINCE_FACEDOWN)));
            $this->assertSame(16, count($player->getCardsByPlace(DynastyCard::STATE_DRAW_DECK)));
            $provinces = $player->getCardsByType(Type::PROVINCE);
            foreach ($provinces as $province) {
                $this->assertNotEmpty($province->getTopCards());
            }
        }

        return $gameId;
    }
}
