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
use App\Message\ChooseStrongholdProvince;
use App\Message\RevealDynastyCard;
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

        $this->assertSame('choose_stronghold', $game->getCurrentPlace());

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
        $game = $this->gameRepository->get($gameId, 'choose_stronghold');
        $this->assertSame(2, $game->getPlayers()->count());
        $john = $game->getPlayers()->get(0);
        $this->assertInstanceOf(Player::class, $john);
        $jane = $game->getPlayers()->get(1);
        $this->assertInstanceOf(Player::class, $jane);

        $this->chooseStrongholdProvince($game, $john, 'Ancestral Lands');
        // no transition because only one player has chosen a stronghold province
        $this->assertSame('choose_stronghold', $game->getCurrentPlace());

        $this->chooseStrongholdProvince($game, $jane, 'Entrenched Position');
        // all automatic transitions happened automatically, this is the first manual transition
        $this->assertSame('reveal_facedown_dynasty_cards', $game->getCurrentPlace());

        return $gameId;
    }

    #[Depends('testChooseStrongholdProvinceHandler')]
    public function testSetupGameTransitionListener(string $gameId): string
    {
        $game = $this->gameRepository->get($gameId, 'reveal_facedown_dynasty_cards');

        // assert that the game is ready to start, everything is in order
        foreach ($game->getPlayers() as $player) {
            // deck shuffled
            $dynastyPositions = $player->getCardsByCurrentPlace(DynastyCard::STATE_DRAW_DECK)->map(
                fn (PhysicalCard $card): ?int => $card->getPosition()
            )->toArray();
            $this->assertSame(count($dynastyPositions), count(array_unique($dynastyPositions)));
            // conflict cards drawn
            $this->assertSame(20, count($player->getCardsByCurrentPlace(ConflictCard::STATE_DRAW_DECK)));
            $this->assertSame(20, $player->getUniqueStack(StackType::ConflictDrawDeck)->getCards()->count());
            $this->assertSame(4, count($player->getCardsByCurrentPlace(ConflictCard::STATE_HAND)));
            $this->assertSame(4, $player->getUniqueStack(StackType::Hand)->getCards()->count());
            // starting honor
            $this->assertGreaterThan(0, $this->playerStateManager->getState($player)->getHonor());
            // filled provinces
            $this->assertSame(16, count($player->getCardsByCurrentPlace(DynastyCard::STATE_DRAW_DECK)));
            $this->assertSame(16, $player->getUniqueStack(StackType::DynastyDrawDeck)->getCards()->count());
            $this->assertSame(4, count($player->getCardsByCurrentPlace(DynastyCard::STATE_PROVINCE_FACEDOWN)));
            foreach ($player->getCardsByClass(ProvinceCard::class) as $province) {
                // counting 2: the province and the dynasty or stronghold card on it
                $this->assertEquals(2, $province->getStack()->getCards()->count());
            }
        }

        return $gameId;
    }

    #[Depends('testSetupGameTransitionListener')]
    public function testRevealDynastyCards(string $gameId): string
    {
        $game = $this->gameRepository->get($gameId, 'reveal_facedown_dynasty_cards');

        foreach ($game->getPlayers() as $player) {
            $this->assertCount(4, $player->getCardsByCurrentPlace(DynastyCard::STATE_PROVINCE_FACEDOWN));
            foreach ($player->getStacks(StackType::RegularProvince) as $stack) {
                foreach ($stack->getCards()->filter(fn (PhysicalCard $card): bool => $card->getCurrentPlace() === DynastyCard::STATE_PROVINCE_FACEDOWN) as $card) {
                    $this->messageBus->dispatch(
                        new RevealDynastyCard($game->getId(), $player->getId(), $card->getId())
                    );
                }
            }
            $this->assertCount(4, $player->getCardsByCurrentPlace(DynastyCard::STATE_PROVINCE_FACEUP));
        }

        $this->assertSame('collect_fate', $game->getCurrentPlace());

        return $gameId;
    }
}
