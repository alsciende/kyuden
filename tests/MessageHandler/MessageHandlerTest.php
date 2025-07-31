<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\DataFixtures\AppFixtures;
use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;
use App\Enum\Type;
use App\Message\ChooseStrongholdProvince;
use App\Tests\AbstractKernelTestCase;
use PHPUnit\Framework\Attributes\Depends;

class MessageHandlerTest extends AbstractKernelTestCase
{
    public function testCreateGameHandler(): string
    {
        $game = $this->createGame();

        $this->assertInstanceOf(Game::class, $game);
        $this->assertSame('join_game', $game->getCurrentPlace());

        return $game->getId();
    }

    #[Depends('testCreateGameHandler')]
    public function testJoinGameHandler(string $gameId): string
    {
        $game = $this->gameRepository->get($gameId);
        $this->assertInstanceOf(Game::class, $game);

        $john = $this->createPlayer($game, AppFixtures::LION_STARTER_DECK, 'john');

        $this->assertSame('join_game', $game->getCurrentPlace());

        $this->assertInstanceOf(Player::class, $john);
        $this->assertSame($game->getId(), $john->getGame()->getId());
        $this->assertSame(1, $game->getPlayers()->count());

        $jane = $this->createPlayer($game, AppFixtures::CRANE_STARTER_DECK, 'jane');

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
        $game = $this->gameRepository->get($gameId, 'place_provinces');
        $this->assertSame(2, $game->getPlayers()->count());
        $john = $game->getPlayers()->get(0);
        $this->assertInstanceOf(Player::class, $john);
        $jane = $game->getPlayers()->get(1);
        $this->assertInstanceOf(Player::class, $jane);

        $this->chooseStrongholdProvince($game, $john, 'Ancestral Lands');
        // no transition because only one player has chosen a stronghold province
        $this->assertSame('place_provinces', $game->getCurrentPlace());

        $this->chooseStrongholdProvince($game, $jane, 'Entrenched Position');
        // all automatic transitions happened automatically, this is the first manual transition
        $this->assertSame('play_cards_from_provinces', $game->getCurrentPlace());

        return $gameId;
    }

    #[Depends('testChooseStrongholdProvinceHandler')]
    public function testSetupGameTransitionListener(string $gameId): string
    {
        $game = $this->gameRepository->get($gameId, 'play_cards_from_provinces');

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
                $this->assertEquals(2, $province->getStack()->getCards()->count());
            }

            $this->assertSame(5, $player->getUniqueStack(StackType::Hand)->getCards()->count());
        }

        return $gameId;
    }
}
