<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Enum\Orientation;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Workflow\Game\DefendingPlayerOpportunityEnterListener;
use App\Workflow\Game\GivenActionOpportunityEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Marking;

class GivenActionOpportunityEnterListenerTest extends AbstractKernelTestCase
{
    public function testTheAttackerGivenTheFirstActionOpportunityGetsIt(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->givenActions(1))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province')
            ->build();
        $event = new EnterEvent($scenario->game(), new Marking());

        self::service(DefendingPlayerOpportunityEnterListener::class)($event);
        self::service(GivenActionOpportunityEnterListener::class)($event);

        self::assertSame([$scenario->player('john')->getId()], self::service(GameStateManager::class)->getState($scenario->game())->activePlayerIds);
        self::assertSame(0, $this->playerStateManager->getState($scenario->player('john'))->getGivenActions());
    }

    public function testTheWindowOpensAsUsualWithoutAnOpportunityGiven(): void
    {
        $scenario = $this->scenario()
            ->atPlace('fate_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('jane')
            ->build();

        self::service(GivenActionOpportunityEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));

        self::assertSame([$scenario->player('jane')->getId()], self::service(GameStateManager::class)->getState($scenario->game())->activePlayerIds);
    }
}
