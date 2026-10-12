<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\DataFixtures\AppFixtures;
use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Entity\CardTypes\ProvinceCard;
use App\Enum\StackType;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;

class GameBuilderTest extends AbstractKernelTestCase
{
    public function testBuildPlacesCardsInStacksWithMatchingWorkflowPlaces(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->strongholdProvince('01-ancestral-lands', as: 'stronghold-province')
                ->province('01-entrenched-position', as: 'province', dynasty: '01-akodo-toturi', dynastyAs: 'toturi')
                ->hand([
                    'banzai' => '01-banzai',
                    '01-fine-katana',
                ]))
            ->withPlayer('jane')
            ->build();

        $john = $scenario->player('john');
        $game = $scenario->game();

        self::assertSame('reveal_facedown_dynasty_cards', $game->getCurrentPlace());
        self::assertCount(2, $game->getPlayers());
        self::assertCount(5, $game->getPhysicalCards());
        // 7 unique stacks + 1 regular province
        self::assertCount(8, $john->getStacks());
        self::assertCount(7, $scenario->player('jane')->getStacks());

        $toturi = $scenario->card('toturi');
        self::assertInstanceOf(DynastyCard::class, $toturi);
        self::assertSame(DynastyCard::STATE_PROVINCE_FACEDOWN, $toturi->getCurrentPlace());
        self::assertSame($scenario->card('province')->getStack(), $toturi->getStack());
        self::assertSame(ProvinceCard::STATE_FACEDOWN, $scenario->card('province')->getCurrentPlace());

        $banzai = $scenario->card('banzai');
        self::assertSame(ConflictCard::STATE_HAND, $banzai->getCurrentPlace());
        self::assertSame(0, $banzai->getPosition());
        self::assertCount(2, $john->getUniqueStack(StackType::Hand)->getCards());

        $state = $this->playerStateManager->getState($john);
        self::assertSame(10, $state->getHonor());
        self::assertSame($scenario->card('stronghold-province')->getId(), $state->getStrongholdProvinceId());

        // the first declared player is the first player by default
        self::assertSame($john->getId(), self::service(GameStateManager::class)->getState($game)->firstPlayerId);
    }

    public function testStarterDeckIsLaidOutAsAfterStartGame(): void
    {
        $scenario = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::LION_STARTER_DECK, [
                'ancestral-lands' => '01-ancestral-lands',
            ]))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->fromStarterDeck(AppFixtures::CRANE_STARTER_DECK))
            ->build();

        $john = $scenario->player('john');
        self::assertCount(50, $john->getPhysicalCards());
        self::assertCount(7, $john->getStacks());
        self::assertCount(24, $john->getUniqueStack(StackType::ConflictDrawDeck)->getCards());
        self::assertCount(20, $john->getUniqueStack(StackType::DynastyDrawDeck)->getCards());
        self::assertSame(StackType::StrongholdProvince, $scenario->card('ancestral-lands')->getStack()->getType());
        self::assertNull($this->playerStateManager->getState($john)->getStrongholdProvinceId());
    }

    public function testPersistedScenarioCanBeLoadedById(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->hand([
                'banzai' => '01-banzai',
            ]))
            ->withPlayer('jane')
            ->persist();

        $game = $this->gameRepository->get($scenario->game()->getId(), 'reveal_facedown_dynasty_cards');
        $card = $this->cardRepository->get($scenario->card('banzai')->getId(), $game);

        self::assertSame($scenario->card('banzai'), $card);
    }

    public function testCardCannotBePutInAStackOfAnotherSide(): void
    {
        $this->expectException(\LogicException::class);

        $this->scenario()
            ->atPlace('reveal_facedown_dynasty_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->hand(['01-akodo-toturi']))
            ->build();
    }
}
