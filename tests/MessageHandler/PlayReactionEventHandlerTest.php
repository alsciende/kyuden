<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ConflictCard;
use App\Message\PlayReactionEvent;
use App\MessageHandler\PlayReactionEventHandler;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

class PlayReactionEventHandlerTest extends AbstractKernelTestCase
{
    public function testAPlayerWithoutTheOpportunityToActPlaysAnEventInAStepWithoutOpportunities(): void
    {
        $scenario = $this->scenario()
            ->atPlace('declare_defenders')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->fate(3)
                ->hand(['glory' => '01-for-greater-glory']))
            ->activePlayer('john')
            ->persist();

        self::service(PlayReactionEventHandler::class)(new PlayReactionEvent(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card('glory')->getId(),
            1,
        ));

        self::assertSame(ConflictCard::STATE_DISCARD_PILE, $scenario->card('glory')->getCurrentPlace());
        self::assertSame(2, self::service(PlayerStateManager::class)->getState($scenario->player('jane'))->getFate());
        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertTrue($state->isActivePlayer($scenario->player('john')));
        self::assertFalse($state->isActivePlayer($scenario->player('jane')));
        self::assertSame(1, self::service(PlayerStateManager::class)->getState($scenario->player('jane'))->getResolvingInterruptsAndReactions());
    }
}
