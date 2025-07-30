<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\DataFixtures\StarterCraneDeckTrait;
use App\DataFixtures\StarterDeckLionTrait;
use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Type;
use App\Message\ChooseStrongholdProvince;
use App\Message\CreateGame;
use App\Message\JoinGame;
use App\Repository\CardRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\State\Manager\PlayerStateManager;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class MessageHandlerTest extends KernelTestCase
{
    use StarterDeckLionTrait;
    use StarterCraneDeckTrait;

    private MessageBusInterface $messageBus;
    private GameRepository $gameRepository;
    private CardRepository $cardRepository;
    private PlayerRepository $playerRepository;
    private PlayerStateManager $playerStateManager;

    #[Before]
    protected function getServices(): void
    {
        $container = static::getContainer();
        // @phpstan-ignore assign.propertyType
        $this->messageBus = $container->get(MessageBusInterface::class);
        // @phpstan-ignore assign.propertyType
        $this->gameRepository = $container->get(GameRepository::class);
        // @phpstan-ignore assign.propertyType
        $this->cardRepository = $container->get(CardRepository::class);
        // @phpstan-ignore assign.propertyType
        $this->playerRepository = $container->get(PlayerRepository::class);
        // @phpstan-ignore assign.propertyType
        $this->playerStateManager = $container->get(PlayerStateManager::class);
    }

    public function testCreateGameHandler(): string
    {
        $gameId = Uuid::v4()->toString();

        $message = new CreateGame($gameId);
        $this->messageBus->dispatch($message);

        $game = $this->gameRepository->find($gameId);

        $this->assertInstanceOf(Game::class, $game);
        $this->assertSame('join_game', $game->getCurrentPlace());

        return $gameId;
    }

    #[Depends('testCreateGameHandler')]
    public function testJoinGameHandler(string $gameId): string
    {
        $game = $this->gameRepository->get($gameId);

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
        $this->messageBus->dispatch($message);

        $john = $this->playerRepository->find($playerId);
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

        $jane = $this->messageBus->dispatch($message);

        $jane = $this->playerRepository->find($playerId);
        $this->assertInstanceOf(Player::class, $jane);
        $this->assertSame($game->getId(), $jane->getGame()->getId());
        $this->assertSame(2, $game->getPlayers()->count());
        $this->assertSame(50, $john->getPhysicalCards()->count());
        $this->assertSame(50, $jane->getPhysicalCards()->count());

        $this->assertSame('place_provinces', $game->getCurrentPlace());

        return $gameId;
    }

    private function chooseStrongholdProvince(Game $game, Player $player, string $title): void
    {
        $province = $this->cardRepository->findOneBy([
            'game' => $game,
            'player' => $player,
            'title' => $title,
        ]);
        $this->assertInstanceOf(ProvinceCard::class, $province);

        $message = new ChooseStrongholdProvince(
            $game->getId(),
            $player->getId(),
            $province->getId()
        );

        $this->messageBus->dispatch($message);
    }

    #[Depends('testJoinGameHandler')]
    public function testChooseStrongholdProvinceHandler(string $gameId): string
    {
        $game = $this->gameRepository->get($gameId);
        $this->assertSame(2, $game->getPlayers()->count());
        $john = $game->getPlayers()->get(0);
        $this->assertInstanceOf(Player::class, $john);
        $jane = $game->getPlayers()->get(1);
        $this->assertInstanceOf(Player::class, $jane);

        $this->chooseStrongholdProvince($game, $john, 'Ancestral Lands');
        $this->assertSame('place_provinces', $game->getCurrentPlace());

        $this->chooseStrongholdProvince($game, $jane, 'Entrenched Position');
        // all automatic transitions happened automatically, this is the first manual transition
        $this->assertSame('play_cards_from_provinces', $game->getCurrentPlace());

        // assert that the game is ready to start, everything is in order
        foreach ($game->getPlayers() as $player) {
            // deck shuffled
            $dynastyPositions = array_map(
                fn (PhysicalCard $card): ?int => $card->getPosition(),
                $player->getCardsByCurrentPlace(DynastyCard::STATE_DRAW_DECK)
            );
            $this->assertSame(count($dynastyPositions), count(array_unique($dynastyPositions)));
            // conflict cards drawn
            $this->assertSame(19, count($player->getCardsByCurrentPlace(ConflictCard::STATE_DRAW_DECK)));
            $this->assertSame(5, count($player->getCardsByCurrentPlace(ConflictCard::STATE_HAND)));
            // starting honor
            $this->assertGreaterThan(0, $this->playerStateManager->getState($player)->getHonor());
            // filled provinces
            $this->assertSame(4, count($player->getCardsByCurrentPlace(DynastyCard::STATE_PROVINCE_FACEDOWN)));
            $this->assertSame(16, count($player->getCardsByCurrentPlace(DynastyCard::STATE_DRAW_DECK)));
            $provinces = $player->getCardsByType(Type::Province);
            foreach ($provinces as $province) {
                // counting 2: the province and the dynasty card on it
                $this->assertEquals(2, $province->getStack()?->getCards()->count());
            }
        }

        return $gameId;
    }
}
