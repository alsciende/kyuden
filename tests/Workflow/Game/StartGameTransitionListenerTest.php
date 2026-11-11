<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\DataFixtures\AppFixtures;
use App\Entity\CardTypes\ProvinceCard;
use App\Entity\CardTypes\StrongholdCard;
use App\Enum\StackType;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Workflow\Game\StartGameTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

class StartGameTransitionListenerTest extends AbstractKernelTestCase
{
    public function testInitPlayer(): void
    {
        $scenario = $this->scenario()
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->deck(AppFixtures::LION_STARTER_DECK))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->deck(AppFixtures::CRANE_STARTER_DECK))
            ->build();
        $john = $scenario->player('john');

        self::assertCount(0, $john->getStacks());

        self::service(StartGameTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([$john->getId(), $scenario->player('jane')->getId()], $state->playerIds);
        self::assertContains($state->firstPlayerId, $state->playerIds);

        self::assertCount(7, $john->getStacks());
        self::assertCount(50, $john->getPhysicalCards());
    }

    public function testStrongholdAtTheBottomOfItsStack(): void
    {
        // the decks of the fixtures list their cards by code: Kyūden Hida (26) after four of the provinces
        $scenario = $this->scenario()
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->deck('john-crab-phoenix'))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->deck(AppFixtures::CRANE_STARTER_DECK))
            ->build();

        self::service(StartGameTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        foreach ($scenario->game()->getPlayers() as $player) {
            $cards = $player->getUniqueStack(StackType::StrongholdProvince)->getOrderedCards();
            $stronghold = array_shift($cards);
            $provinces = $cards;
            self::assertInstanceOf(StrongholdCard::class, $stronghold);
            self::assertCount(5, $provinces);
            self::assertContainsOnlyInstancesOf(ProvinceCard::class, $provinces);
        }
    }
}
