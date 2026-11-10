<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\DataFixtures\AppFixtures;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;
use App\Message\ChooseStrongholdProvince;
use App\Message\MulliganConflictCards;
use App\Message\MulliganDynastyCards;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;

/**
 * The mulligans of setup through the bus: the steps follow each other automatically.
 */
class MulliganTest extends AbstractKernelTestCase
{
    /**
     * @return array{Game, Player, Player} the game once both players have chosen their stronghold province, and its
     *                                     players in player order (john, jane)
     */
    private function setUpGame(bool $mulligan): array
    {
        $game = $this->createGame($mulligan);
        self::assertInstanceOf(Game::class, $game);
        $john = $this->createPlayer($game, AppFixtures::LION_STARTER_DECK, 'john');
        $jane = $this->createPlayer($game, AppFixtures::CRANE_STARTER_DECK, 'jane');
        self::assertInstanceOf(Player::class, $john);
        self::assertInstanceOf(Player::class, $jane);
        foreach ([[$john, 'Ancestral Lands'], [$jane, 'Entrenched Position']] as [$player, $title]) {
            $province = $this->cardRepository->findOneBy(['game' => $game, 'player' => $player, 'title' => $title]);
            self::assertInstanceOf(ProvinceCard::class, $province);
            $this->messageBus->dispatch(new ChooseStrongholdProvince($game->getId(), $player->getId(), $province->getId()));
        }

        return [$game, $john, $jane];
    }

    /**
     * @return array{Player, Player} the first player, then the other one
     */
    private function inPlayerOrder(Game $game, Player $john, Player $jane): array
    {
        return self::service(GameStateManager::class)->getState($game)->firstPlayerId === $john->getId() ? [$john, $jane] : [$jane, $john];
    }

    public function testThePlayersMulliganInPlayerOrderBeforeTheFirstRound(): void
    {
        [$game, $john, $jane] = $this->setUpGame(true);
        [$first, $second] = $this->inPlayerOrder($game, $john, $jane);
        $state = self::service(GameStateManager::class);

        self::assertSame('dynasty_mulligan', $game->getCurrentPlace());
        self::assertSame([$first->getId()], $state->getState($game)->activePlayerIds);
        // the starting hand is drawn after the dynasty mulligan
        self::assertCount(0, $first->getUniqueStack(StackType::Hand)->getCards());
        // each player looks at the cards of their provinces
        $looks = array_filter($this->logLines($game, $second), fn (string $line): bool => str_contains($line, 'looks at'));
        self::assertCount(8, $looks);

        $card = array_first(array_filter($first->getPhysicalCards()->getValues(), fn (PhysicalCard $card): bool => $card instanceof DynastyCard && $card->getStack()->getType() === StackType::RegularProvince));
        self::assertInstanceOf(DynastyCard::class, $card);
        $this->messageBus->dispatch(new MulliganDynastyCards($game->getId(), $first->getId(), [$card->getId()]));
        self::assertSame('dynasty_mulligan', $game->getCurrentPlace());
        self::assertSame([$second->getId()], $state->getState($game)->activePlayerIds);

        $this->messageBus->dispatch(new MulliganDynastyCards($game->getId(), $second->getId()));
        self::assertSame('conflict_mulligan', $game->getCurrentPlace());
        self::assertSame([$first->getId()], $state->getState($game)->activePlayerIds);
        self::assertCount(4, $first->getUniqueStack(StackType::Hand)->getCards());
        self::assertCount(4, $second->getUniqueStack(StackType::Hand)->getCards());

        $hand = $first->getUniqueStack(StackType::Hand)->getOrderedCards();
        $this->messageBus->dispatch(new MulliganConflictCards($game->getId(), $first->getId(), [$hand[0]->getId(), $hand[1]->getId()]));
        $this->messageBus->dispatch(new MulliganConflictCards($game->getId(), $second->getId()));

        self::assertSame('reveal_facedown_dynasty_cards', $game->getCurrentPlace());
        self::assertCount(4, $first->getUniqueStack(StackType::Hand)->getCards());
        self::assertCount(20, $first->getUniqueStack(StackType::ConflictDrawDeck)->getCards());
        self::assertCount(16, $first->getUniqueStack(StackType::DynastyDrawDeck)->getCards());
    }

    public function testAGameWithoutMulliganGoesStraightToTheFirstRound(): void
    {
        [$game, $john] = $this->setUpGame(false);

        self::assertSame('reveal_facedown_dynasty_cards', $game->getCurrentPlace());
        self::assertCount(4, $john->getUniqueStack(StackType::Hand)->getCards());
        self::assertSame([], array_filter($this->logLines($game, $john), fn (string $line): bool => str_contains($line, 'looks at')));
    }
}
